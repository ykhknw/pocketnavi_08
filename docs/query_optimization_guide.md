# クエリ最適化ガイド

## 実装済みの最適化

### 1. キーワード検索の最適化
- **最小文字数制限**: 2文字以上のキーワードのみ検索対象
- **検索対象カラムの削減**: 8カラム → 3カラム（title, titleEn, location）
- **建築家検索の分離**: 建築家検索が必要な場合のみJOINを実行

### 2. JOINの最適化
- **条件付きJOIN**: 建築家検索が必要な場合のみJOINを実行
- **カウントクエリの最適化**: 建築家検索が不要な場合はJOINを省略

### 3. 安全対策
- **クエリタイムアウト**: 30秒でタイムアウト
- **検索結果数の上限**: 10,000件まで
- **ページ番号の上限**: 100ページまで

## 推奨されるインデックスの追加

以下のインデックスを追加することで、クエリのパフォーマンスがさらに向上します：

### 1. buildings_table_4 テーブル

```sql
-- タイトル検索用インデックス
CREATE INDEX idx_buildings_title ON buildings_table_4(title);
CREATE INDEX idx_buildings_titleEn ON buildings_table_4(titleEn);

-- 場所検索用インデックス
CREATE INDEX idx_buildings_location ON buildings_table_4(location);

-- 都道府県検索用インデックス
CREATE INDEX idx_buildings_prefectures ON buildings_table_4(prefectures);

-- 完成年検索用インデックス
CREATE INDEX idx_buildings_completionYears ON buildings_table_4(completionYears);

-- 建築種別検索用インデックス
CREATE INDEX idx_buildings_buildingTypes ON buildings_table_4(buildingTypes);

-- 複合インデックス（よく使われる組み合わせ）
CREATE INDEX idx_buildings_photo_type ON buildings_table_4(has_photo, buildingTypes);
```

### 2. building_architects テーブル

```sql
-- 建築物ID検索用インデックス（既存の可能性あり）
CREATE INDEX idx_building_architects_building_id ON building_architects(building_id);
CREATE INDEX idx_building_architects_architect_id ON building_architects(architect_id);
```

### 3. architect_compositions_2 テーブル

```sql
-- 建築家ID検索用インデックス
CREATE INDEX idx_architect_compositions_architect_id ON architect_compositions_2(architect_id);
CREATE INDEX idx_architect_compositions_individual_id ON architect_compositions_2(individual_architect_id);
```

### 4. individual_architects_3 テーブル

```sql
-- 建築家名検索用インデックス
CREATE INDEX idx_individual_architects_name_ja ON individual_architects_3(name_ja);
CREATE INDEX idx_individual_architects_name_en ON individual_architects_3(name_en);
CREATE INDEX idx_individual_architects_slug ON individual_architects_3(slug);
```

## フルテキスト検索の検討（将来の最適化）

現在はLIKE検索を使用していますが、より高速なフルテキスト検索（FULLTEXT INDEX）の導入を検討してください：

```sql
-- フルテキストインデックスの作成
ALTER TABLE buildings_table_4 
ADD FULLTEXT INDEX ft_buildings_search (title, titleEn, location);

-- フルテキスト検索の使用例
SELECT * FROM buildings_table_4 
WHERE MATCH(title, titleEn, location) AGAINST('test' IN BOOLEAN MODE);
```

## パフォーマンス測定

最適化の効果を測定するため、以下のログを確認してください：

- `COUNT QUERY EXECUTION TIME`: カウントクエリの実行時間
- `SEARCH QUERY EXECUTION TIME`: 検索クエリの実行時間
- `Total`: 全体の実行時間

## 期待される改善

最適化により、以下の改善が期待されます：

1. **キーワード検索**: 約70%の高速化（8カラム → 3カラム）
2. **JOINの削減**: 建築家検索が不要な場合、約50%の高速化
3. **タイムアウト対策**: 30秒でタイムアウトし、サーバーリソースを保護

## 注意事項

- インデックスの追加は、INSERT/UPDATEのパフォーマンスに若干の影響を与える可能性があります
- インデックスの追加前に、本番環境のデータ量を確認してください
- 定期的にインデックスの使用状況を確認し、不要なインデックスは削除してください
