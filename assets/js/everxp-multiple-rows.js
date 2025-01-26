document.addEventListener("DOMContentLoaded", function () {
    const sliders = document.querySelectorAll(".everxp-multi-slider");

    sliders.forEach((slider) => {
        const slides = slider.querySelectorAll(".multi-slide");
        console.log('sdfsdf')
        let currentIndex = 0;

        // Set initial active slide
        slides[currentIndex].classList.add("active");

        // Rotate slides
        setInterval(() => {
            slides[currentIndex].classList.remove("active");
            currentIndex = (currentIndex + 1) % slides.length; // Loop back to the first slide
            slides[currentIndex].classList.add("active");
        }, parseInt(slider.getAttribute("data-duration")) || 5000);
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
