document.addEventListener("DOMContentLoaded", function () {

    // ✅ Init session start timestamp + session ID
    if (!sessionStorage.getItem("everxp_session_start")) {
        sessionStorage.setItem("everxp_session_start", Date.now());
    }

    if (!sessionStorage.getItem("everxp_session_id")) {
        const sid = Math.random().toString(36).substr(2) + Date.now();
        sessionStorage.setItem("everxp_session_id", sid);
    }

    function getTimeOnSite() {
        const start = parseInt(sessionStorage.getItem("everxp_session_start"), 10);
        return start ? Math.floor((Date.now() - start) / 1000) : 0;
    }

    function getSessionId() {
        return sessionStorage.getItem("everxp_session_id") || "unknown";
    }

    function getClientMetadata() {
        return {
            current_page: window.location.pathname,
            page_title: document.title,
            referrer_url: document.referrer || "direct",
            time_on_site: getTimeOnSite(),
            language: navigator.language || "unknown",
            screen_resolution: `${window.screen.width}x${window.screen.height}`,
            device_type: /Mobile|Android|iP/.test(navigator.userAgent) ? "mobile" : "desktop",
            session_id: getSessionId()
        };
    }

    function trackPageView() {
        const utms = getEverXPUTMs();
        const banners = document.querySelectorAll(".everxp-text-output, .everxp-multi-text-output");
        let tracked = false;

        banners.forEach(element => {
            const folderId = element.getAttribute("data-folder-id");
            const headingId = element.getAttribute("data-heading-id") || null;
            if (!folderId) return;

            tracked = true;

            const eventData = {
                cache_buster: Date.now(),
                eventType: "pageview",
                eventData: {
                    ...getClientMetadata(),
                    utm_parameters: {
                        utm_source: "everxp",
                        utm_medium: document.referrer || "direct",
                        utm_campaign: folderId,
                        utm_term: headingId
                    }
                }
            };
            sendEvent(eventData);

            // Store banner view flag with page info
            sessionStorage.setItem("everxp_banner_viewed", JSON.stringify({
                folderId,
                headingId,
                lastUrl: window.location.pathname
            }));
        });

        // Fallback: if no banners and no session banner, fire session_pageview
        let sessionTracked = false;
        if (!tracked && utms?.utm_campaign && utms?.utm_term) {
            const eventData = {
                cache_buster: Date.now(),
                eventType: "session_pageview",
                eventData: {
                    ...getClientMetadata(),
                    utm_parameters: {
                        utm_source: "everxp",
                        utm_medium: document.referrer || "direct",
                        utm_campaign: utms.utm_campaign,
                        utm_term: utms.utm_term
                    }
                }
            };
            sendEvent(eventData);
            sessionTracked = true;
        }

        // Follow-up if previously viewed banner but now no banners
        const stored = sessionStorage.getItem("everxp_banner_viewed");
        const noBanners = banners.length === 0;

        if (!tracked && !sessionTracked && stored && noBanners) {
            const { folderId, headingId, lastUrl } = JSON.parse(stored);

            if (window.location.pathname !== lastUrl) {
                const followupEvent = {
                    cache_buster: Date.now(),
                    eventType: "followup_pageview",
                    eventData: {
                        ...getClientMetadata(),
                        utm_parameters: {
                            utm_source: "everxp",
                            utm_medium: document.referrer || "direct",
                            utm_campaign: folderId,
                            utm_term: headingId
                        }
                    }
                };
                sendEvent(followupEvent);

                // Update lastUrl to current path
                sessionStorage.setItem("everxp_banner_viewed", JSON.stringify({
                    folderId,
                    headingId,
                    lastUrl: window.location.pathname
                }));
            }
        }

        if (utms) {
            document.cookie = "everxp_attributed=true; path=/; max-age=1800";
        }
    }

    // ✅ Execute Pageview Tracking on Load
    trackPageView();



    function storeUTMs() {
        let urlParams = new URLSearchParams(window.location.search);
        let utmData = {};

        ["utm_source", "utm_medium", "utm_campaign", "utm_term"].forEach(key => {
            if (urlParams.has(key)) {
                utmData[key] = urlParams.get(key);
            }
        });

        if (utmData.utm_source === "everxp") {
            // Store full UTM params in a cookie (30 days)
            document.cookie = "everxp_utms=" + JSON.stringify(utmData) + "; path=/; max-age=" + (60 * 60 * 24 * 30);

            // Set session attribution cookie (valid for 30 minutes)
            document.cookie = "everxp_attributed=true; path=/; max-age=1800";

            console.log("✅ EverXP UTM Stored:", utmData);
        }
    }


    storeUTMs();

    // ✅ Function to Get Stored UTM Parameters from Cookie
    function getEverXPUTMs() {
        let match = document.cookie.match(/(^|;) ?everxp_utms=([^;]*)(;|$)/);
        return match ? JSON.parse(decodeURIComponent(match[2])) : null;
    }

    // ✅ Track Link Clicks (Only if From EverXP Headings)
    document.body.addEventListener("click", function (event) {
        let target = event.target.closest("a");
        let parentHeading = target ? target.closest(".everxp-text-output") : null;

        if (target && target.href && parentHeading) {            
            event.preventDefault();
            console.log("🚀 EverXP Link Click Detected:", target.href);

            // On banner click
            document.cookie = "everxp_attributed=true; path=/; max-age=1800";

            let eventData = {
                cache_buster: new Date().getTime(),
                eventType: "link_click",
                eventData: {
                    ...getClientMetadata(),
                    url: target.href,
                    heading_text: parentHeading ? parentHeading.innerText : "",
                    utm_parameters: extractUTMs(target.href),
                }
            };

            sendEvent(eventData, () => {
                window.location.href = target.href;
            });
        }
    });

    // ✅ Track Checkout Initiated (Button Click + Page Load)
    document.body.addEventListener("click", function (event) {
        if (event.target.matches(".checkout-button")) {
            let storedUTMs = getEverXPUTMs();
            if (!storedUTMs) return;

            console.log("🚀 EverXP Checkout Initiated Detected");

            let eventData = {
                cache_buster: new Date().getTime(),
                eventType: "checkout_initiated",
                eventData: {
                    ...getClientMetadata(),
                    utm_parameters: storedUTMs
                }
            };

            sendEvent(eventData);
        }
    });

    if (window.location.pathname.includes("/checkout")) {
        let storedUTMs = getEverXPUTMs();
        if (storedUTMs) {
            console.log("🚀 EverXP Checkout Initiated (Page Load)");

            let eventData = {
                cache_buster: new Date().getTime(),
                eventType: "checkout_initiated",
                eventData: {
                    ...getClientMetadata(),
                    utm_parameters: storedUTMs
                }
            };

            sendEvent(eventData);
        }
    }

    // ✅ Track WooCommerce Add to Cart (Only if EverXP Attributed)
    document.body.addEventListener("click", function (event) {
        let target = event.target.closest(".add-to-cart, .single_add_to_cart_button");
        if (!target) return; // Exit if no target

        let storedUTMs = getEverXPUTMs();
        if (!storedUTMs) return; // Only track if EverXP UTMs exist

        let productId = target.getAttribute("data-product-id") || 
                        target.closest("form.cart")?.querySelector("input[name=product_id]")?.value;

        let productName = target.getAttribute("data-product-name") || 
                          target.closest(".product")?.querySelector(".product_title")?.innerText;

        let price = target.getAttribute("data-price") || 
                    target.closest(".product")?.querySelector(".woocommerce-Price-amount")?.innerText;

        // ✅ Ignore if product data is missing
        if (!productId || !productName) {
            return;
        }

        console.log("🚀 EverXP Add to Cart Detected:", productName);

        let eventData = {
            cache_buster: new Date().getTime(),
            eventType: "add_to_cart",
            eventData: {
                ...getClientMetadata(),
                product_id: productId,
                product_name: productName,
                price: price || "unknown",
                utm_parameters: storedUTMs
            }
        };

        setTimeout(() => {
            sendEvent(eventData);
        }, 200); // Short delay to prevent duplicate sends
    });


    // ✅ Track WooCommerce Purchase (Only if EverXP Attributed)
    if (window.location.href.includes("order-received")) {
        let storedUTMs = getEverXPUTMs();
        if (!storedUTMs) return;

        // ✅ Extract order ID from URL path
        const match = window.location.pathname.match(/order-received\/(\d+)/);
        const orderId = match ? match[1] : "unknown";

        // ✅ Try to extract total amount from DOM, fallback to unknown
        const totalAmount = document.querySelector(".order-total .woocommerce-Price-amount")?.textContent?.trim() || "unknown";

        console.log("🚀 EverXP Purchase Completed:", orderId, totalAmount);

        let eventData = {
            cache_buster: new Date().getTime(),
            eventType: "purchase",
            eventData: {
                ...getClientMetadata(),
                order_id: orderId,
                total_price: totalAmount,
                utm_parameters: storedUTMs
            }
        };

        sendEvent(eventData);
    }



    // ✅ Track WordPress User Registration (Only if EverXP Attributed)
    document.body.addEventListener("submit", function (event) {
        if (event.target.matches("#registerform")) {
            let storedUTMs = getEverXPUTMs();
            if (!storedUTMs) return;

            console.log("🚀 EverXP User Registration Detected");

            let eventData = {
                cache_buster: new Date().getTime(),
                eventType: "user_registration",
                eventData: {
                    ...getClientMetadata(),
                    utm_parameters: storedUTMs
                }
            };

            sendEvent(eventData);
        }
    });

    // ✅ Track Contact Form 7 Submissions (Only if EverXP Attributed)
    document.addEventListener("wpcf7mailsent", function (event) {
        let storedUTMs = getEverXPUTMs();
        if (!storedUTMs) return;

        let formId = event.detail.contactFormId;

        console.log("🚀 EverXP Form Submission Detected:", formId);

        let eventData = {
            cache_buster: new Date().getTime(),
            eventType: "form_submission",
            eventData: {
                ...getClientMetadata(),
                form_id: formId,
                utm_parameters: storedUTMs
            }
        };

        sendEvent(eventData);
    });

    // Utility: Extract UTMs from URLs
    function extractUTMs(url) {
        let params = new URL(url).searchParams;
        let utmData = {};

        ["utm_source", "utm_medium", "utm_campaign", "utm_term"].forEach(key => {
            if (params.has(key)) {
                utmData[key] = params.get(key);
            }
        });

        console.log("Extracted UTM Data:", utmData);
        return utmData;
    }

    function sendEvent(eventData, callback) {
        console.log("Sending Event:", eventData);

        fetch(everxpTracker.ajax_url, {
            method: "POST",
            headers: { 
                "Content-Type": "application/json",
                "Authorization": "Bearer " + everxpTracker.auth_token
            },
            body: JSON.stringify({
                ...eventData, // Spread existing event data
                user_data: everxpTracker.user_data,
                user_identifier: everxpTracker.user_identifier
            }),
        })
        .then(response => response.json())
        .then(data => {
            console.log("✅ EverXP Event Sent Successfully", everxpTracker.user_data);
            if (callback) callback();
        })
        .catch(error => console.error("❌ Event Tracking Error:", error));
    }
});
