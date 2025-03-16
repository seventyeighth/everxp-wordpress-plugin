document.addEventListener("DOMContentLoaded", function () {
    console.log("EverXP Event Tracking Loaded");

    // ✅ Function to Track Pageviews for EverXP Text Blocks
    function trackPageView() {
        document.querySelectorAll(".everxp-text-output, .everxp-multi-text-output").forEach(element => {
            let folderId = element.getAttribute("data-folder-id");
            let headingId = element.getAttribute("data-heading-id") || null;

            if (!folderId) return; // Skip if no folder ID

            // console.log(`🚀 Tracking EverXP Page View: Folder ID = ${folderId}, Heading ID = ${headingId}`);

            let eventData = {
                cache_buster: new Date().getTime(),
                eventType: "pageview",
                eventData: {
                    referrer_url: document.referrer || "direct",
                    utm_parameters: {
                        utm_source: 'everxp',
                        utm_medium: document.referrer || "direct",
                        utm_campaign: folderId,
                        utm_term: headingId
                    }
                }
            };

            sendEvent(eventData);
        });
    }

    // ✅ Execute Pageview Tracking on Load
    trackPageView();

    // ✅ Store EverXP UTM Parameters in a Cookie (for non-link events)
    function storeUTMs() {
        let urlParams = new URLSearchParams(window.location.search);
        let utmData = {};

        ["utm_source", "utm_medium", "utm_campaign", "utm_term"].forEach(key => {
            if (urlParams.has(key)) {
                utmData[key] = urlParams.get(key);
            }
        });

        if (utmData.utm_source === "everxp") {
            document.cookie = "everxp_utms=" + JSON.stringify(utmData) + "; path=/; max-age=" + (60 * 60 * 24 * 30); // Store for 30 days
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

            let eventData = {
                cache_buster: new Date().getTime(),
                eventType: "link_click",
                eventData: {
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
    jQuery(document).on("checkout_order_received", function (event, orderId) {
        let storedUTMs = getEverXPUTMs();
        if (!storedUTMs) return;

        console.log("🚀 EverXP Purchase Completed:", orderId);

        let eventData = {
            cache_buster: new Date().getTime(),
            eventType: "purchase",
            eventData: {
                order_id: orderId || "unknown",
                utm_parameters: storedUTMs
            }
        };

        sendEvent(eventData);
    });

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

    // Utility: Send Event to WordPress AJAX
    function sendEvent(eventData, callback) {
        console.log("Sending Event:", eventData);

        fetch(everxpTracker.ajax_url + "?action=" + everxpTracker.ajax_action, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(eventData),
        })
        .then(response => response.json())
        .then(data => {
            // console.log("✅ EverXP Event Sent Successfully");
            if (callback) callback();
        })
        .catch(error => console.error("❌ Event Tracking Error:", error));
    }
});
