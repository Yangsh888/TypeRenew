(function(){
    var script = document.getElementById('renewgo-client-script');
    if (!script) return;
    
    var c = {};
    try {
        c = JSON.parse(script.getAttribute('data-config') || '{}');
    } catch(e) {}
    
    var base = script.getAttribute('data-base') || '';
    if (!c || !base) {
        return;
    }
    
    var hostPath = c.hostPath || [];
    var exactHost = {};
    (c.exactHost || []).forEach(function(h){
        exactHost[String(h || "").toLowerCase()] = 1;
    });

    function wh(url){
        try{
            var u = new URL(url, location.href);
            var h = (u.hostname || "").toLowerCase();
            if(!h || h === c.siteHost){
                return true;
            }
            if(exactHost[h]){
                return true;
            }
            for(var i = 0; i < (c.wildHost || []).length; i++){
                var s = String(c.wildHost[i] || "").toLowerCase();
                if(!s){
                    continue;
                }
                if(h === s || h.endsWith("." + s)){
                    return true;
                }
            }
            var hp = (h + (u.pathname || "/")).replace(/^\/+/, "");
            for(var j = 0; j < hostPath.length; j++){
                var p = String(hostPath[j] || "").toLowerCase();
                if(p && hp.indexOf(p) === 0){
                    return true;
                }
            }
            return false;
        } catch(e) {
            return true;
        }
    }

    function enc(v){
        try{
            return btoa(unescape(encodeURIComponent(v))).replace(/\+/g,"-").replace(/\//g,"_").replace(/=+$/,"");
        }catch(e){
            return "";
        }
    }

    function rw(a){
        if(!a || a.dataset.renewgo === "1" || a.dataset.renewgoSkip !== undefined){
            return;
        }
        var href = a.getAttribute("href");
        if(!href || href.charAt(0) === "#" || href.indexOf("mailto:") === 0 || href.indexOf("javascript:") === 0){
            return;
        }
        var u;
        try{
            u = new URL(href, location.href);
        }catch(e){
            return;
        }
        if(!/^https?:$/i.test(u.protocol)){
            return;
        }
        if(wh(u.href)){
            return;
        }
        var code = enc(u.href);
        if(!code){
            return;
        }
        a.setAttribute("href", base + "/go/" + code);
        a.dataset.renewgo = "1";
        if(c.openInNewTab){
            a.setAttribute("target", "_blank");
        }
        var rel = (a.getAttribute("rel") || "").split(/\s+/).filter(Boolean);
        ["nofollow","noopener","noreferrer"].forEach(function(x){
            if(rel.indexOf(x) < 0){
                rel.push(x);
            }
        });
        a.setAttribute("rel", rel.join(" "));
    }

    function scan(root){
        if ((root || document).querySelectorAll) {
            (root || document).querySelectorAll("a[href]").forEach(rw);
        }
    }
    
    scan(document);
    
    var mo = new MutationObserver(function(ms){
        ms.forEach(function(m){
            if (m.addedNodes) {
                m.addedNodes.forEach(function(n){
                    if(n && n.nodeType === 1){
                        if(n.matches && n.matches("a[href]")){
                            rw(n);
                        }
                        scan(n);
                    }
                });
            }
        });
    });
    
    mo.observe(document.documentElement, {childList: true, subtree: true});
})();
