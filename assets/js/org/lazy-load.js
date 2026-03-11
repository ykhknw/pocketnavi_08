// Lazy Loading Implementation for Phase 4.2
(function() {
    'use strict';
    
    // Intersection Observer for lazy loading
    const observerOptions = {
        root: null,
        rootMargin: '50px',
        threshold: 0.1
    };
    
    // Lazy load images
    function lazyLoadImages() {
        const images = document.querySelectorAll('img[data-src]');
        
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        img.classList.add('loaded');
                        observer.unobserve(img);
                    }
                });
            }, observerOptions);
            
            images.forEach(img => imageObserver.observe(img));
        } else {
            // Fallback for older browsers
            images.forEach(img => {
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                img.classList.add('loaded');
            });
        }
    }
    
    // Lazy load CSS
    function lazyLoadCSS() {
        const cssLinks = document.querySelectorAll('link[rel="preload"][as="style"]');
        cssLinks.forEach(link => {
            if (link.onload) {
                link.onload();
            }
        });
    }
    
    // Lazy load JavaScript
    function lazyLoadJS() {
        const jsScripts = document.querySelectorAll('script[data-src]');
        jsScripts.forEach(script => {
            const newScript = document.createElement('script');
            newScript.src = script.dataset.src;
            newScript.async = true;
            if (script.dataset.defer) {
                newScript.defer = true;
            }
            script.parentNode.replaceChild(newScript, script);
        });
    }
    
    // Lazy load map
    function lazyLoadMap() {
        const mapContainer = document.getElementById('map');
        if (!mapContainer) return;
        
        const mapObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Load Leaflet CSS
                    const leafletCSS = document.createElement('link');
                    leafletCSS.rel = 'stylesheet';
                    leafletCSS.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                    document.head.appendChild(leafletCSS);
                    
                    // Load Leaflet JS
                    const leafletJS = document.createElement('script');
                    leafletJS.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                    leafletJS.onload = function() {
                        // Initialize map after Leaflet is loaded
                        if (typeof initMap === 'function') {
                            const buildings = window.buildingsData || [];
                            const center = buildings.length > 0 ? 
                                [buildings[0].lat, buildings[0].lng] : 
                                [35.6762, 139.6503];
                            initMap(center, buildings);
                        }
                    };
                    document.head.appendChild(leafletJS);
                    
                    mapObserver.unobserve(entry.target);
                }
            });
        }, observerOptions);
        
        mapObserver.observe(mapContainer);
    }
    
    // Lazy load non-critical JavaScript
    function lazyLoadNonCriticalJS() {
        // Load main.js after page load
        const mainJS = document.createElement('script');
        mainJS.src = '/assets/js/main.min.js';
        mainJS.defer = true;
        document.head.appendChild(mainJS);
        
        // Load Lucide icons after page load
        const lucideJS = document.createElement('script');
        lucideJS.src = 'https://unpkg.com/lucide@latest/dist/umd/lucide.js';
        lucideJS.onload = function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        };
        document.head.appendChild(lucideJS);
    }
    
    // Initialize lazy loading
    function initLazyLoading() {
        // Load critical resources immediately
        lazyLoadCSS();
        
        // Load non-critical resources after page load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                lazyLoadImages();
                lazyLoadMap();
                lazyLoadNonCriticalJS();
            });
        } else {
            lazyLoadImages();
            lazyLoadMap();
            lazyLoadNonCriticalJS();
        }
    }
    
    // Start lazy loading
    initLazyLoading();
    
    // Add loading states
    function addLoadingStates() {
        // Add loading class to images
        const images = document.querySelectorAll('img[data-src]');
        images.forEach(img => {
            img.classList.add('lazy');
            img.style.opacity = '0';
            img.style.transition = 'opacity 0.3s ease';
        });
        
        // Add loading class to map
        const mapContainer = document.getElementById('map');
        if (mapContainer) {
            mapContainer.innerHTML = '<div class="loading"><div class="loading-spinner"></div><div class="loading-text">地図を読み込み中...</div></div>';
        }
    }
    
    // Add CSS for loading states
    function addLoadingCSS() {
        const style = document.createElement('style');
        style.textContent = `
            .lazy {
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            .loaded {
                opacity: 1;
            }
            .loading {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem;
                color: #6c757d;
            }
            .loading-spinner {
                display: inline-block;
                width: 20px;
                height: 20px;
                border: 3px solid #f3f3f3;
                border-top: 3px solid #2563eb;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin-right: 0.5rem;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Initialize loading states
    addLoadingCSS();
    addLoadingStates();
    
})();
