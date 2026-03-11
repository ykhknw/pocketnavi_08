<?php
// データベース接続
//$host = 'localhost';
$host = 'mysql320.phy.heteml.lan';
//$db   = 'shinkenchiku_db';
$db   = '_shinkenchiku_db';
$user = '_shinkenchiku_db';
$pass = 'yyvcnp8x';
$charset = 'utf8mb4';
$websites_table = 'architect_websites_3'; #

$self = basename(__FILE__); // 現在のPHPファイル名を取得

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("データベース接続エラー: " . htmlspecialchars($e->getMessage()));
}

// ページネーション設定
$limit = 10;
$page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
$offset = ($page - 1) * $limit;

// クエリパラメータ取得
$uid = isset($_GET['uid']) ? trim($_GET['uid']) : '';
$title = isset($_GET['title']) ? trim($_GET['title']) : '';
$architect = isset($_GET['architect']) ? trim($_GET['architect']) : '';
$buildingType = isset($_GET['buildingType']) ? trim($_GET['buildingType']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
//$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$architectIdToFilter = isset($_GET['architect_id']) ? trim($_GET['architect_id']) : '';//$_GET['architect_id'];

$geoSearch = isset($_GET['geo']) ? true : false;
$userLat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$userLng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;
//$radiusKm = 10; // 検索半径（キロメートル）
$radiusKm = 5; // 検索半径（キロメートル）


//$whereSql = " WHERE b.title NOT LIKE '%月評%' ";
$whereClauses = ["b.title NOT LIKE '%月評%'"];

$params = [];

// --- 条件の追加 ---
if ($geoSearch && $userLat !== null && $userLng !== null) {
    // WHERE句用のプレースホルダ
    $whereClauses[] = "(6371 * ACOS(COS(RADIANS(:userLat_w1)) * COS(RADIANS(b.lat)) * COS(RADIANS(b.lng) - RADIANS(:userLng_w)) + SIN(RADIANS(:userLat_w2)) * SIN(RADIANS(b.lat)))) < :radius_w ";
    $params[':userLat_w1'] = $userLat;
    $params[':userLat_w2'] = $userLat;
    $params[':userLng_w'] = $userLng;
    $params[':radius_w'] = $radiusKm;

} elseif ($uid !== '') {
    $whereClauses[] = "b.uid = :uid ";
    $params[':uid'] = $uid;

} elseif($architectIdToFilter !== '') {
    $whereClauses[] = "ba.architect_id = :architectIdFilter ";
    $params[':architectIdFilter'] = $architectIdToFilter;

} else {
    if ($title !== '') {
        $whereClauses[] = "(b.title LIKE :title1 OR b.titleEn LIKE :title2)";
        $params[':title1'] = "%$title%";
        $params[':title2'] = "%$title%";
    }
    if ($architect !== '') {
        $whereClauses[] = "(a.architectJa LIKE :architect1 OR a.architectEn LIKE :architect2)";
        $params[':architect1'] = '%' . $architect . '%';
        $params[':architect2'] = '%' . $architect . '%';
    }
    if ($buildingType !== '') {
        $whereClauses[] = "b.buildingTypes LIKE :buildingType";
        $params[':buildingType'] = "%$buildingType%";
    }
    if ($location !== '') {
        $whereClauses[] = "b.location LIKE :location";
        $params[':location'] = "%$location%";
    }
}

// WHERE句を構築
$whereSql = " WHERE " . implode(" AND ", $whereClauses);


// --- 件数取得 ---
$countSql = "
    SELECT COUNT(DISTINCT b.id)
    FROM buildings_table_2 b
    LEFT JOIN building_architects ba ON b.id = ba.building_id
    LEFT JOIN architects_table a ON ba.architect_id = a.architect_id
    $whereSql
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $limit);





// --- データ取得クエリ ---
$sqlSelectPart = "SELECT b.*,
                    GROUP_CONCAT(a.architectJa ORDER BY ba.architect_order SEPARATOR ' / ') AS architectJa,
                    GROUP_CONCAT(a.architect_id ORDER BY ba.architect_order SEPARATOR ',') AS architectIds";

// 地理検索が有効な場合にのみ距離計算カラムを追加
if ($geoSearch && $userLat !== null && $userLng !== null) {
    // SELECT句用のプレースホルダ名に '_s' を付加し、WHERE句とは異なる名前にする
    $sqlSelectPart .= ", (6371 * ACOS(COS(RADIANS(:userLat_s1)) * COS(RADIANS(b.lat)) * COS(RADIANS(b.lng) - RADIANS(:userLng_s)) + SIN(RADIANS(:userLat_s2)) * SIN(RADIANS(b.lat)))) AS distanceKm";
}

$sql = "
    $sqlSelectPart
    FROM buildings_table_2 b
    LEFT JOIN building_architects ba ON b.id = ba.building_id
    LEFT JOIN architects_table a ON ba.architect_id = a.architect_id
    $whereSql
    GROUP BY b.id
";

// ここにソート条件を追加
if ($geoSearch && $userLat !== null && $userLng !== null) {
    // geoSearchが真の場合は、計算された距離 (distanceKm) で昇順ソート
    $sql .= " ORDER BY distanceKm ASC";
} else {
    // それ以外の場合は、既存のuid降順ソート
    $sql .= " ORDER BY b.uid DESC";
}

$sql .= "
    LIMIT :limit OFFSET :offset
";




$stmt = $pdo->prepare($sql);

// すべてのパラメータをまとめる
$allParams = array_merge($params, [
    ':limit' => $limit,
    ':offset' => $offset
]);

// SELECT句で追加した新しいプレースホルダ用のパラメータもここで追加
// geoSearchがtrueの場合のみ追加される
if ($geoSearch && $userLat !== null && $userLng !== null) {
    $allParams[':userLat_s1'] = $userLat;
    $allParams[':userLat_s2'] = $userLat;
    $allParams[':userLng_s'] = $userLng;
}

// Debugging: 最終的なSQLとパラメータを確認
//echo "<pre>Final SQL: " . htmlspecialchars($sql) . "</pre>";
//echo "<pre>Final Params: "; print_r($allParams); echo "</pre>";

$stmt->execute($allParams); // エラーが発生している行
$buildings = $stmt->fetchAll();


//スクリーンショット
$architect_id = $_GET['architect_id'] ?? null;
$websites = [];

if ($architect_id) {
//    $pdo = new PDO('mysql:host=HOST;dbname=DB;charset=utf8', 'USER', 'PASS');
    $stmt = $pdo->prepare("SELECT website_id, url, title FROM $websites_table WHERE architect_id = :id AND invalid = 0");
    $stmt->execute([':id' => $architect_id]);
    $websites = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 現在のクエリパラメータを保持（ページ以外）
$queryParams = $_GET;
unset($queryParams['page']); // ページ番号は毎回変更するため除外

// クエリ文字列に変換（例: &title=xxx&location=yyy）
$queryString = http_build_query($queryParams);

?>


<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>建築作品データベース pocket NAVI.</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <link rel="icon" href="/favicon.ico" type="image/x-icon">
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    .numbered-icon {
      background: url('https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png') no-repeat center;
      background-size: contain;
      text-align: center;
      line-height: 41px;
      font-weight: bold;
      color: white;
      width: 25px;
      height: 41px;
    }
    .marker-number {
      position: relative;
      top: 5px;
      font-size: 14px;
    }
    #mapList {
      height: 400px;
      margin-top: 40px;
      margin-bottom: 40px;
    }
    li[data-href] {
      cursor: pointer;
    }
    li[data-href] a {
      pointer-events: none;
    }

.img-hover-zoom {
  transition: transform 0.3s ease;
}
.img-hover-zoom:hover {
  transform: scale(1.03);
}
.overlay-title {
  font-size: 0.9rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

  </style>
</head>
<body>


<header>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-0">
    <div class="container">
      <a class="navbar-brand" href="<?= $self ?>">建築作品データベース pocket NAVI.</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent"
        aria-controls="navbarContent" aria-expanded="false" aria-label="メニュー切替">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarContent">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item"><a class="nav-link" href="<?= $self ?>">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="/about.php">About</a></li>
          <li class="nav-item"><a class="nav-link" href="/contact.php">Contact Us</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- 🔍 検索フォーム -->
  <div class="bg-light border-bottom py-3">
    <div class="container">
      <form method="GET" class="row g-3">

        <div class="col-md-3">
          <input type="text" name="title" class="form-control" placeholder="建物名で検索"
            value="<?= isset($_GET['title']) ? htmlspecialchars($_GET['title']) : '' ?>">
        </div>
        <div class="col-md-3">
          <input type="text" name="architect" class="form-control" placeholder="設計者名で検索"
            value="<?= isset($_GET['architect']) ? htmlspecialchars($_GET['architect']) : '' ?>">
        </div>
        <div class="col-md-3">
          <input type="text" name="buildingType" class="form-control" placeholder="用途で検索"
            value="<?= isset($_GET['buildingType']) ? htmlspecialchars($_GET['buildingType']) : '' ?>">
        </div>
        <div class="col-md-3">
          <input type="text" name="location" class="form-control" placeholder="地名で検索"
            value="<?= isset($_GET['location']) ? htmlspecialchars($_GET['location']) : '' ?>">
        </div>

        <div class="col-12 col-md-3">
          <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-outline-secondary w-100">Reset</a>
        </div>
        <div class="col-12 col-md-6">
          <button type="submit" class="btn btn-primary w-100">Search</button>
        </div>
        <div class="col-12 col-md-3">
          <button type="button" id="getLocationBtn" class="btn btn-success w-100">現在地から探す</button>
        </div>

      </form>
    </div>
  </div>
</header>


  <div class="container">
    <h2 class="mb-4">
      <?php if (!empty($uid)): ?>
        <?= htmlspecialchars($buildings[0]['title']) ?>
      <?php elseif (!empty($architectIdToFilter)): ?>
        <?= htmlspecialchars($buildings[0]['architectJa']) ?>
      <?php else: ?>
        検索結果（<?= number_format($total) ?> 件中 <?= number_format($offset + 1) ?> - <?= number_format(min($offset + $limit, $total)) ?> 件）
      <?php endif; ?>
    </h2>


<div class="row g-3">
  <?php foreach ($buildings as $index => $b): ?>
<!--    <div class="col-12 col-sm-6 col-md-6">  タブレット以上で2列表示 -->
    <div class="<?= !empty($uid) ? 'col-12' : 'col-12 col-sm-6 col-md-6' ?>"><!-- $uid が 空でない場合には、カードを 1列表示（横幅100%） にする -->
      <div class="card  <?php if (!$uid) echo 'clickable'; ?>"
        <?php if (!$uid): ?>
          data-href="<?= $self ?>?uid=<?= urlencode($b['uid']) ?>"
          onclick="location.href=this.getAttribute('data-href')"
          style="cursor: pointer;" 
        <?php endif; ?>>

        <div class="card-body" pb-2>
<h5 class="card-title">
  <a href="<?= $self ?>?uid=<?= htmlspecialchars($b['uid']) ?>">
    <?= !$uid ? ($offset + $index + 1) . '. ' : '' ?><?= htmlspecialchars($b['title']) ?>
  </a>

        <?php if (isset($b['distanceKm'])): ?>
            &nbsp;(<?= number_format($b['distanceKm'], 1) ?> km)
        <?php endif; ?>

</h5>


          <!-- ・付きリスト -->
          <ul class="ps-3 mb-0" style="list-style-type: disc;"> <!-- Bootstrapでpadding-left, 手動でdisc指定 -->
            <?php if ((!empty($b['titleEn']) || !empty($b['completionYears'])) && $uid): ?>
              <li><i><?= htmlspecialchars($b['titleEn'] ?? '') ?><?= !empty($b['titleEn']) && !empty($b['completionYears']) ? ' - ' : '' ?><?= htmlspecialchars($b['completionYears'] ?? '') ?></i></li>
            <?php endif; ?>

              <?php if ($uid): ?>


<!--サムネイル表示する場合-->
<?php
if (isset($b['thumbnailUrl'])) {
//if (isset($_GET['thumb']) && isset($b['thumbnailUrl'])) {
    $url = htmlspecialchars($b['thumbnailUrl'], ENT_QUOTES, 'UTF-8');
    echo "<img src=\"$url\" width=\"70%\">";
}
?>
<!--/サムネイル表示する場合-->


            <li>
                <a href="https://www.google.com/search?tbm=isch&q=<?= urlencode($b['title']) ?>" target="_blank">
                  Googleで画像検索
                </a>&nbsp;|&nbsp;<a href="https://www.bing.com/images/search?q=<?= urlencode($b['title']) ?>" target="_blank">
                  Bingで画像検索
                </a>
            </li>
            <li>
                <a href="<?= $self ?>?architect_id=<?= $b['architectIds'] ?>">
                  <?= htmlspecialchars($b['architectJa']) ?>
                </a>
            </li>
              <?php else: ?>
<span class="badge bg-secondary text-white text-wrap"
      style="white-space: normal;">
  <?= htmlspecialchars($b['architectJa']) ?>
</span>
              <?php endif; ?>


              <?php if ($uid): ?>
            <li>
                <?= htmlspecialchars($b['location'] ?? '-') ?>
            </li>
              <?php else: ?>
<?php if (!empty($b['prefectures'])): ?>
    <br>
    <span class="badge bg-primary"><?= htmlspecialchars($b['prefectures']) ?></span>
<?php endif; ?>
              <?php endif; ?>



              <?php if ($uid): ?>

    <?php if (!empty($b['prefectures'])): ?>
<li>
        <a href="<?= htmlspecialchars($self) ?>?location=<?= urlencode($b['prefectures']) ?>">
            <?= htmlspecialchars($b['prefectures']) ?>を探す
        </a>
</li>
    <?php endif; ?>
    <?php if (!empty($b['lat']) && !empty($b['lng'])): ?>
<li>
        <a href="<?= htmlspecialchars($self) ?>?geo=1&lat=<?= urlencode($b['lat']) ?>&lng=<?= urlencode($b['lng']) ?>&radius=5">
            この建築周辺を探す
        </a>
</li>

    <?php endif; ?>

              <?php endif; ?>


              <?php
              if (empty($b['buildingTypes'])) {
                  echo '<li>-</li>';

              } elseif (empty($uid)) {
                  echo '<br>';

//                  echo htmlspecialchars($b['buildingTypes']);
$types = explode('/', $b['buildingTypes']);
$links = [];
foreach ($types as $type) {
    $trimmedType = trim($type);
//    $escapedType = htmlspecialchars($trimmedType);
    $links[] = '<span class="badge bg-success text-white text-wrap" style="white-space: normal;">' . htmlspecialchars($trimmedType) . '</span>';

}
echo implode(' ', $links) ;

              } else {

                  echo '<li>';
                  $types = explode('/', $b['buildingTypes']);
                  $links = [];
                  foreach ($types as $type) {
                      $trimmedType = trim($type);
                      $escapedType = htmlspecialchars($trimmedType);
                      $links[] = "<a href=\"{$self}?buildingType=" . urlencode($trimmedType) . "\">{$escapedType}</a>";
                  }
                  echo implode(' / ', $links) . " を探す";
                  echo '</li>';

              }
              ?>
          </ul>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>



    <!-- ページャー（省略付き） -->
    <?php if ($uid === ''): ?>

<nav aria-label="ページネーション" class="mt-4">
  <ul class="pagination justify-content-center">
    
    <?php if ($page > 1): ?>
      <li class="page-item">
        <a class="page-link" href="?page=<?= $page - 1 ?><?= $queryString ? '&' . $queryString : '' ?>">前へ</a>
      </li>
    <?php endif; ?>

    <?php
    $range = 2; // 現在ページ前後の表示数
    $ellipsisBefore = false;
    $ellipsisAfter = false;

    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
            if ($i == $page) {
                echo "<li class='page-item active'><span class='page-link'>{$i}</span></li>";
            } else {
                $url = "?page={$i}" . ($queryString ? "&{$queryString}" : '');
                echo "<li class='page-item'><a class='page-link' href='{$url}'>{$i}</a></li>";
            }
        } elseif ($i < $page - $range && !$ellipsisBefore) {
            echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
            $ellipsisBefore = true;
        } elseif ($i > $page + $range && !$ellipsisAfter) {
            echo "<li class='page-item disabled'><span class='page-link'>…</span></li>";
            $ellipsisAfter = true;
        }
    }
    ?>

    <?php if ($page < $totalPages): ?>
      <li class="page-item">
        <a class="page-link" href="?page=<?= $page + 1 ?><?= $queryString ? '&' . $queryString : '' ?>">次へ</a>
      </li>
    <?php endif; ?>

  </ul>
</nav>

    <?php endif; ?>


<?php if (!empty($websites)): ?>
  <h3 class="mt-4 mb-3">関連サイト（<?= count($websites) ?>件）</h3>

<div class="row g-3">
  <?php foreach ($websites as $site): ?>
    <div class="col-12 col-sm-6 col-md-4">
      <div class="card h-100 border-0 shadow-sm position-relative">
        <a href="<?= htmlspecialchars($site['url']) ?>" target="_blank" class="d-block position-relative overflow-hidden" style="text-decoration: none;">


<?php
$filename = "shot_{$site['website_id']}.png";
$imageDir = __DIR__ . "/screen_shots_2/";
$imagePath = $imageDir . $filename;
$imageExists = file_exists($imagePath);
$imageUrl = $imageExists ? "/screen_shots_2/$filename" : "/screen_shots_2/placeholder.png";
//$imageUrl = "/screen_shots_2/$filename";
?>
<img src="<?= htmlspecialchars($imageUrl) ?>"
     class="card-img-top img-hover-zoom"
     alt="<?= htmlspecialchars($site['title']) ?>"
     title="<?= htmlspecialchars($site['title']) ?>">


          <!-- タイトルのオーバーレイ -->
<div class="overlay-title text-white bg-dark bg-opacity-75 p-2 position-absolute bottom-0 w-100 text-truncate">
  <?= htmlspecialchars($site['title'] ?: 'No title') ?>
</div>

        </a>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php endif; ?>


  </div>

  <div class="container">
    <div id="mapList"></div>
  </div>

<?php if ($uid): ?>

<?php
// Google Maps リンク（閲覧用）
$viewLink = "https://maps.google.com/?q=" . urlencode($b['lat']) . "," . urlencode($b['lng']);

// Google Maps 経路検索リンク（現在地から目的地）
$gmapLink = "https://www.google.com/maps/dir/?api=1&destination=" . urlencode($b['lat']) . "," . urlencode($b['lng']);
?>

<div class="container text-center mt-3 mb-4">
  <a href="<?= htmlspecialchars($viewLink) ?>" target="_blank" class="btn btn-outline-primary me-2">
    View on Google Maps
  </a>
  <a href="<?= htmlspecialchars($gmapLink) ?>" target="_blank" class="btn btn-outline-success">
    Get Directions
  </a>
</div>


<?php endif; ?>

  <footer class="bg-light text-center py-4 mt-5">
    <p class="mb-0">© <?= date("Y") ?> 建築作品データベース pocket NAVI.</p>
  </footer>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var map = L.map('mapList').setView([35.6895, 139.6917], 6);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
      }).addTo(map);

      var offset = <?= $offset ?>;
      var data = <?php
        $json = [];
        foreach ($buildings as $index => $b) {
          if (!empty($b['lat']) && !empty($b['lng'])) {
            $json[] = [
              'lat' => (float)$b['lat'],
              'lng' => (float)$b['lng'],
              'titleJa' => $b['title'],
              'titleEn' => $b['titleEn'],
              'uid' => $b['uid'],
              'markerNumber' => $offset + $index + 1
            ];
          }
        }
        echo json_encode($json, JSON_UNESCAPED_UNICODE);
      ?>;

      var bounds = L.latLngBounds();

      data.forEach(function (item) {
        var numberedIcon = L.divIcon({
          className: 'numbered-icon',
          html: '<div class="marker-number">' + item.markerNumber + '</div>',
          iconSize: [25, 41],
          iconAnchor: [12, 41]
        });

        var marker = L.marker([item.lat, item.lng], { icon: numberedIcon }).addTo(map);
        var popupContent = '<div style="min-width:150px;">' +
          '<a href="<?= $self ?>?uid=' + item.uid + '">' + item.titleJa + '</a><br><i>' +
          item.titleEn + '</i></div>';

        marker.bindPopup(popupContent);
        bounds.extend([item.lat, item.lng]);
      });

      if (data.length > 0) {

if (data.length === 1) {
  map.setView([data[0].lat, data[0].lng], 16); // ズームレベル指定
} else {
  map.fitBounds(bounds);
}

      }
    });
  </script>

<script>
  document.getElementById('getLocationBtn')?.addEventListener('click', function () {
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(function (position) {
        var lat = position.coords.latitude;
        var lng = position.coords.longitude;
        var radius = 5;
        window.location.href = '<?= $self ?>?geo=1&lat=' + lat + '&lng=' + lng + '&radius=' + radius;
      }, function (error) {
        alert('位置情報の取得に失敗しました。ブラウザの設定を確認してください。');
      });
    } else {
      alert('このブラウザは位置情報取得に対応していません。');
    }
  });
</script>



</body>
</html>
