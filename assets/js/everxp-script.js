document.addEventListener("DOMContentLoaded", function () {
    const animatedElements = document.querySelectorAll(".everxp-animated");

    animatedElements.forEach((el) => {
        const effect = el.getAttribute("data-effect");
        const duration = parseInt(el.getAttribute("data-duration"), 10) || 1000;

        if (effect === "fade" || effect === "slide") {
            // Delay activation for fade and slide
            setTimeout(() => {
                el.classList.add("everxp-active");
            }, duration);
        }
        // Bounce effect handled entirely by CSS animation
    });
});
