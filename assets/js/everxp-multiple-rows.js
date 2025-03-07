document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".everxp-multi-slider").forEach((slider) => {
        const slides = Array.from(slider.querySelectorAll(".multi-slide"));
        let currentIndex = 0;

        function showNextSlide() {
            slides.forEach((slide, index) => {
                slide.classList.toggle("active", index === currentIndex);
                slide.style.display = index === currentIndex ? "inline-block" : "none";
            });

            currentIndex = (currentIndex + 1) % slides.length; // Move to the next slide
        }

        // Initialize first slide
        showNextSlide();
        setInterval(showNextSlide, parseInt(slider.getAttribute("data-duration")) || 5000);
    });
});




document.querySelectorAll('.everxp-news-ticker').forEach((ticker) => {
    const duration = parseInt(ticker.getAttribute('data-duration'), 10) || 10000; // Default: 10s

    if (ticker.classList.contains('vertical')) {
        // Vertical scrolling ticker
        ticker.querySelector('.ticker-content').style.animation = `ticker-scroll-vertical ${duration}ms linear infinite`;
    } else {
        // Horizontal scrolling ticker
        ticker.querySelector('.ticker-content').style.animation = `ticker-scroll-horizontal ${duration}ms linear infinite`;
    }
});


document.addEventListener("DOMContentLoaded", function () {
    const tickers = document.querySelectorAll(".everxp-news-ticker");

    tickers.forEach((ticker) => {
        const content = ticker.querySelector(".ticker-content");

        // Pause on hover
        ticker.addEventListener("mouseover", () => {
            content.style.animationPlayState = "paused";
        });

        ticker.addEventListener("mouseout", () => {
            content.style.animationPlayState = "running";
        });
    });
});
