// Timer functionality
const Timer = (() => {
    let intervalId = null;
    let countdownId = null;
    let remainingTime = 0;
    let isPaused = false;
    let isFullscreen = false;

    // Initialize timer
    const init = () => {
        setupEventListeners();
        updateDisplay();
    };

    // Setup event listeners
    const setupEventListeners = () => {
        document
            .getElementById("startTimerBtn")
            .addEventListener("click", start);
        document
            .getElementById("pauseTimerBtn")
            .addEventListener("click", pause);
        document
            .getElementById("resetTimerBtn")
            .addEventListener("click", reset);
        document
            .getElementById("timerInterval")
            .addEventListener("change", updateInterval);
        document
            .getElementById("fullscreenBtn")
            .addEventListener("click", toggleFullscreen);

        // Fullscreen change event
        document.addEventListener("fullscreenchange", handleFullscreenChange);
    };

    // Start timer
    const start = () => {
        if (intervalId) return; // Already running

        const interval = getInterval();
        remainingTime = remainingTime || interval;
        isPaused = false;

        // Update buttons state
        updateButtonStates("running");

        // Start countdown
        countdownId = setInterval(() => {
            if (remainingTime > 0) {
                remainingTime--;
                updateDisplay();

                // Warning animation at 5 seconds
                if (remainingTime === 5) {
                    document
                        .querySelector(".timer-value")
                        .classList.add("timer-warning");
                }
            } else {
                // Time's up - spin the wheel
                WheelOfFortune.spin();
                remainingTime = interval;

                // Remove warning class
                document
                    .querySelector(".timer-value")
                    .classList.remove("timer-warning");
            }
        }, 1000);

        // Store the interval ID for auto-spin
        intervalId = true;
    };

    // Pause timer
    const pause = () => {
        if (!intervalId || isPaused) return;

        isPaused = true;
        clearInterval(countdownId);
        countdownId = null;

        updateButtonStates("paused");
    };

    // Reset timer
    const reset = () => {
        clearInterval(countdownId);
        intervalId = null;
        countdownId = null;
        remainingTime = 0;
        isPaused = false;

        updateButtonStates("stopped");
        updateDisplay();

        // Remove warning class
        document
            .querySelector(".timer-value")
            .classList.remove("timer-warning");
    };

    // Get interval from input
    const getInterval = () => {
        const input = document.getElementById("timerInterval");
        return parseInt(input.value) || 30;
    };

    // Update interval
    const updateInterval = () => {
        if (!intervalId && !isPaused) {
            remainingTime = 0;
            updateDisplay();
        }
    };

    // Update display
    const updateDisplay = () => {
        const display = document.getElementById("timerValue");
        const time = remainingTime || getInterval();

        const minutes = Math.floor(time / 60);
        const seconds = time % 60;

        display.textContent = `${minutes.toString().padStart(2, "0")}:${seconds
            .toString()
            .padStart(2, "0")}`;
    };

    // Update button states
    const updateButtonStates = (state) => {
        const startBtn = document.getElementById("startTimerBtn");
        const pauseBtn = document.getElementById("pauseTimerBtn");
        const resetBtn = document.getElementById("resetTimerBtn");

        switch (state) {
            case "running":
                startBtn.disabled = true;
                pauseBtn.disabled = false;
                resetBtn.disabled = false;
                break;
            case "paused":
                startBtn.disabled = false;
                pauseBtn.disabled = true;
                resetBtn.disabled = false;
                break;
            case "stopped":
                startBtn.disabled = false;
                pauseBtn.disabled = true;
                resetBtn.disabled = true;
                break;
        }
    };

    // Toggle fullscreen mode
    const toggleFullscreen = () => {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch((err) => {
                console.error("Error entering fullscreen:", err);
            });
        } else {
            document.exitFullscreen();
        }
    };

    // Handle fullscreen change
    const handleFullscreenChange = () => {
        isFullscreen = !!document.fullscreenElement;
        const btn = document.getElementById("fullscreenBtn");

        if (isFullscreen) {
            btn.innerHTML = '<i data-lucide="minimize"></i>';
            document.body.classList.add("fullscreen-active");
        } else {
            btn.innerHTML = '<i data-lucide="maximize"></i>';
            document.body.classList.remove("fullscreen-active");
        }

        // Re-initialize Lucide icons
        lucide.createIcons();
    };

    // Public API
    return {
        init,
        start,
        pause,
        reset,
        isRunning: () => !!intervalId && !isPaused,
    };
})();

// Initialize when DOM is ready
document.addEventListener("DOMContentLoaded", Timer.init);
