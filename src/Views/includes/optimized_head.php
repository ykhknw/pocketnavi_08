<!-- Optimized Head Section for Phase 4.2 -->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($pageTitle); ?> - PocketNavi</title>

<!-- Critical CSS (inline) -->
<style>
/* Critical CSS for above-the-fold content */
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:0}
.container{max-width:1200px;margin:0 auto;padding:0 15px}
.row{display:flex;flex-wrap:wrap;margin:0 -15px}
.col-lg-8{flex:0 0 66.666667%;max-width:66.666667%;padding:0 15px}
.col-lg-4{flex:0 0 33.333333%;max-width:33.333333%;padding:0 15px}
.card{background:#fff;border:1px solid #e9ecef;border-radius:12px;box-shadow:0 2px 4px rgba(0,0,0,.1)}
.card-body{padding:1.25rem}
.btn{display:inline-block;padding:.5rem 1rem;font-size:.9rem;font-weight:500;text-align:center;text-decoration:none;border:1px solid transparent;border-radius:6px;cursor:pointer;transition:all .2s ease}
.btn-primary{background:#2563eb;border-color:#2563eb;color:#fff}
.btn-primary:hover{background:#1d4ed8;border-color:#1d4ed8}
.loading{display:flex;align-items:center;justify-content:center;padding:2rem;color:#6c757d}
</style>

<!-- Preload critical resources -->
<link rel="preload" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" as="style">
<link rel="preload" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" as="script">
<link rel="preload" href="https://unpkg.com/lucide@latest/dist/umd/lucide.js" as="script">

<!-- Non-critical CSS (deferred) -->
<link rel="preload" href="/assets/css/style.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="/assets/css/style.min.css"></noscript>

<!-- Bootstrap CSS (deferred) -->
<link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></noscript>

<!-- Leaflet CSS (deferred) -->
<link rel="preload" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"></noscript>

<!-- DNS prefetch for external resources -->
<link rel="dns-prefetch" href="//unpkg.com">
<link rel="dns-prefetch" href="//cdn.jsdelivr.net">
<link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
<link rel="dns-prefetch" href="//tile.openstreetmap.org">

<!-- Resource hints -->
<link rel="preconnect" href="https://unpkg.com">
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link rel="preconnect" href="https://tile.openstreetmap.org">
