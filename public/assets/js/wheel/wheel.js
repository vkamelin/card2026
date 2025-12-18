// Wheel of Fortune with Canvas Implementation
const WheelOfFortune = (() => {
    let canvas, ctx;
    let sectors = [];
    let currentRotation = 0;
    let isSpinning = false;
    let spinVelocity = 0;
    let selectedSector = null;
    let canSpin = false;
    let sectorImages = {}; // To store preloaded images
    
    // Initialize wheel
    const init = () => {
        canvas = document.getElementById("wheel");
        if (!canvas) return;
        
        ctx = canvas.getContext("2d");
        
        // Set canvas size
        const size = Math.min(canvas.parentElement.offsetWidth, 500);
        canvas.width = size;
        canvas.height = size;
        
        // Setup event listeners
        setupEventListeners();
        
        // Load wheel data
        loadWheelData();
        
        // Initial draw
        draw();
        
        // Start animation loop
        animate();
    };
    
    // Setup event listeners
    const setupEventListeners = () => {
        const spinButton = document.getElementById("spinButton");
        if (spinButton) {
            spinButton.addEventListener("click", handleSpinClick);
        }
        
        // Handle window resize
        window.addEventListener("resize", () => {
            if (!canvas) return;
            const size = Math.min(canvas.parentElement.offsetWidth, 500);
            canvas.width = size;
            canvas.height = size;
            draw();
        });
    };
    
    // Load wheel data from server
    const loadWheelData = async () => {
        try {
            // Show loading state
            const spinBtnElement = document.getElementById("spinButton");
            if (spinBtnElement) {
                spinBtnElement.disabled = true;
                spinBtnElement.textContent = "Загрузка...";
            }
            
            // Get Telegram Web App initData
            let initData = '';
            if (window.Telegram && window.Telegram.WebApp) {
                initData = window.Telegram.WebApp.initData;
                // Expand the Web App to full height
                window.Telegram.WebApp.expand();
            }
            
            // Fetch wheel data
            const response = await fetch('/api/wheel/init', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Telegram-Init-Data': initData
                }
            });
            
            const data = await response.json();

            if (data.status !== 'success') {
                throw new Error(data.message || 'Ошибка загрузки данных');
            }
            
            // Process the data
            sectors = data.sectors || [];
            canSpin = data.has_spin || false;
            
            // Update button state
            const spinBtnElement2 = document.getElementById("spinButton");
            if (spinBtnElement2) {
                spinBtnElement2.disabled = !canSpin;
                spinBtnElement2.innerHTML = canSpin ? '<span>КРУТИТЬ</span><i data-lucide="rotate-cw"></i>' : 'Недоступно';
                // Reinitialize Lucide icons
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
            
            // Draw the wheel
            draw();

            // Preload sector images
            preloadSectorImages();
            
        } catch (error) {
            console.error('Ошибка загрузки данных колеса:', error);
            showError('Ошибка загрузки: ' + error.message);
        }
    };

    // Preload sector images
    const preloadSectorImages = () => {
        sectors.forEach((sector, index) => {
            if (sector.image && sector.image.trim() !== '') {
                const img = new Image();
                img.onload = () => {
                    sectorImages[index] = img;
                    // Redraw the wheel when an image is loaded
                    draw();
                };
                img.onerror = () => {
                    console.error('Ошибка загрузки изображения:', sector.image);
                };
                // Assuming images are stored in the public/uploads/sectors directory
                img.src = '/uploads/sectors/' + sector.image;
            }
        });
    };
    
    // Handle spin button click
    const handleSpinClick = async () => {
        if (isSpinning || !canSpin) return;
        
        try {
            // Get Telegram Web App initData
            let initData = '';
            if (window.Telegram && window.Telegram.WebApp) {
                initData = window.Telegram.WebApp.initData;
            }
            
            // Send spin request
            const response = await fetch('/api/wheel/spin', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Telegram-Init-Data': initData,
                    'Content-Type': 'application/json'
                }
            });
            
            const data = await response.json();

            if (data.status !== 'success') {
                throw new Error(data.message || 'Ошибка прокрутки');
            }
            
            // Animate the wheel
            const prizeIndex = data.sector;
            const rotations = 5; // Number of full rotations
            const segmentAngle = 360 / sectors.length;
            const targetRotation = rotations * 360 + (360 - (prizeIndex * segmentAngle + segmentAngle/2)) - (currentRotation % 360);
            
            // Start spinning with velocity
            isSpinning = true;
            spinVelocity = Math.min(targetRotation / 30, 0.8); // Adjust speed based on distance
            // Add spinning class to container
            const wheelContainer = canvas.parentElement;
            if (wheelContainer) {
                wheelContainer.classList.add('wheel-spinning');
            }
            
            // Disable spin button
            const spinBtnElement = document.getElementById("spinButton");
            if (spinBtnElement) {
                spinBtnElement.disabled = true;
                spinBtnElement.innerHTML = '<span>Крутим...</span>';
            }
            
            // Store result for later display
            selectedSector = sectors[data.sector];
            
        } catch (error) {
            console.error('Ошибка прокрутки колеса:', error);
            showError('Ошибка прокрутки: ' + error.message);
            // Remove spinning class from container if it was added
            const wheelContainer = canvas ? canvas.parentElement : null;
            if (wheelContainer && wheelContainer.classList.contains('wheel-spinning')) {
                wheelContainer.classList.remove('wheel-spinning');
            }
            
            // Re-enable spin button
            const spinBtnElement = document.getElementById("spinButton");
            if (spinBtnElement) {
                spinBtnElement.disabled = false;
                spinBtnElement.innerHTML = '<span>КРУТИТЬ</span><i data-lucide="rotate-cw"></i>';
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
        }
    };
    
    // Draw the wheel
    const draw = () => {
        if (!ctx || !canvas) return;
        
        const centerX = canvas.width / 2;
        const centerY = canvas.height / 2;
        const radius = Math.min(centerX, centerY) - 10;
        
        // Clear canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Draw sectors
        const anglePerSector = (2 * Math.PI) / sectors.length;
        
        sectors.forEach((sector, index) => {
            const startAngle = index * anglePerSector + currentRotation;
            const endAngle = (index + 1) * anglePerSector + currentRotation;
            
            // Draw sector
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, startAngle, endAngle);
            ctx.closePath();
            ctx.fillStyle = getSectorColor(index);
            ctx.fill();
            
            // Draw border
            ctx.strokeStyle = "#fff";
            ctx.lineWidth = 3;
            ctx.stroke();

            // Draw content (emoji, image or text)
            ctx.save();
            ctx.translate(centerX, centerY);
            ctx.rotate(startAngle + anglePerSector / 2);
            ctx.textAlign = "right";
            ctx.fillStyle = "#fff";
            ctx.font = `bold ${Math.min(20, radius / 10)}px var(--font-display)`;

            // Check if sector has an emoji
            if (sector.emoji && sector.emoji.trim() !== '') {
                // Display large emoji instead of text
                ctx.font = `bold ${Math.min(30, radius / 6)}px var(--font-display)`;
                ctx.fillText(sector.emoji, radius - 20, 5);
            } else if (sector.image && sector.image.trim() !== '' && sectorImages[index]) {
                // Display image instead of text/emoji with max size 40x40px
                const img = sectorImages[index];
                const maxWidth = 40;
                const maxHeight = 40;
                let drawWidth = img.width;
                let drawHeight = img.height;

                // Scale image to fit within max dimensions while maintaining aspect ratio
                if (drawWidth > maxWidth || drawHeight > maxHeight) {
                    const ratio = Math.min(maxWidth / drawWidth, maxHeight / drawHeight);
                    drawWidth *= ratio;
                    drawHeight *= ratio;
                }

                // Draw the image
                ctx.drawImage(img, radius - drawWidth - 10, -drawHeight / 2, drawWidth, drawHeight);
            } else if (sector.image && sector.image.trim() !== '') {
                // Display placeholder for loading image
                ctx.fillText("[IMG]", radius - 20, 5);
            } else {
                // Display text if no emoji or image
                ctx.fillText(formatPrizeText(sector), radius - 20, 5);
            }
            
            ctx.restore();
        });
        
        // Draw center circle
        ctx.beginPath();
        ctx.arc(centerX, centerY, 30, 0, 2 * Math.PI);
        ctx.fillStyle = "var(--bg-primary)";
        ctx.fill();
        ctx.strokeStyle = "var(--accent-primary)";
        ctx.lineWidth = 4;
        ctx.stroke();
    };
    
    // Get sector color based on index
    const getSectorColor = (index) => {
        const colors = [
            '#FF9999', '#66CC99', '#6699FF', '#FFCC66',
            '#CC99FF', '#99CCFF', '#FF9966', '#66FFCC',
            '#CCCC66', '#99FF99', '#FF66CC', '#66FF99'
        ];
        return colors[index % colors.length];
    };
    
    // Format prize text for display
    const formatPrizeText = (prize) => {
        // If sector has emoji, return just the emoji
        if (prize.emoji && prize.emoji.trim() !== '') {
            return prize.emoji;
        }

        // If sector has image, return empty string since we display the image
        if (prize.image && prize.image.trim() !== '') {
            return "";
        }

        // Otherwise, use the original logic
        switch (prize.type) {
            case 'discount':
                return "Скидка\n" + prize.value + "%";
            case 'free_item':
                return "Бесплатно\n" + prize.value;
            case 'free_delivery':
                return "Беспл.\nдоставка";
            case 'consolation':
                return prize.name;
            default:
                return prize.name;
        }
    };
    
    // Animation loop
    const animate = () => {
        if (isSpinning && spinVelocity > 0) {
            currentRotation += spinVelocity;
            spinVelocity *= 0.985; // Friction
            
            // Stop when velocity is very low
            if (spinVelocity < 0.005) {
                isSpinning = false;
                spinVelocity = 0;
                onSpinComplete();
            }
            
            draw();
        } else if (!isSpinning) {
            draw();
        }
        
        requestAnimationFrame(animate);
    };
    
    // Handle spin completion
    const onSpinComplete = () => {
        // Show result
        showResult();
        // Remove spinning class from container
        const wheelContainer = canvas ? canvas.parentElement : null;
        if (wheelContainer && wheelContainer.classList.contains('wheel-spinning')) {
            wheelContainer.classList.remove('wheel-spinning');
        }
        
        // Re-enable spin button with appropriate text
        const spinBtnElement = document.getElementById("spinButton");
        if (spinBtnElement) {
            spinBtnElement.disabled = false;
            if (selectedSector && selectedSector.type === 'consolation') {
                spinBtnElement.innerHTML = '<span>Попробовать снова</span>';
            } else {
                spinBtnElement.innerHTML = '<span>Поздравляем!</span>';
            }
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
        
        // Trigger confetti for big wins
        if (selectedSector && 
            (selectedSector.type === 'discount' && parseInt(selectedSector.value) >= 30) ||
            selectedSector.type === 'free_item') {
            triggerConfetti();
        }
    };
    
    // Show spin result
    const showResult = () => {
        if (!selectedSector) return;
        
        // Create result display element if it doesn't exist
        let resultElement = document.getElementById('wheel-result');
        if (!resultElement) {
            resultElement = document.createElement('div');
            resultElement.id = 'wheel-result';
            resultElement.className = 'wheel-result';
            document.querySelector('.wheel-section').appendChild(resultElement);
        }
        
        // Format result content
        let resultContent = '';
        if (selectedSector.type === 'consolation') {
            resultContent = `
                <div class="result-content">
                    <h2>Увы!</h2>
                    <p>Повезёт в следующий раз!</p>
                    <button class="close-result">Закрыть</button>
                </div>
            `;
        } else {
            resultContent = `
                <div class="result-content">
                    <h2>🎉 Поздравляем!</h2>
                    <div class="prize-display">${selectedSector.display_text || selectedSector.name}</div>
                    <div class="promo-code">${selectedSector.promo_code || ''}</div>
                    <p>Скопируйте промокод и используйте его в нашем заведении!</p>
                    <button class="close-result">Закрыть</button>
                </div>
            `;
        }
        
        resultElement.innerHTML = resultContent;
        resultElement.style.display = 'block';
        
        // Add close event listener
        const closeBtn = resultElement.querySelector('.close-result');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                resultElement.style.display = 'none';
            });
        }
    };
    
    // Trigger confetti animation
    const triggerConfetti = () => {
        const colors = ["#FF6B6B", "#4ECDC4", "#45B7D1", "#F39C12", "#27AE60"];
        
        for (let i = 0; i < 50; i++) {
            setTimeout(() => {
                const confetti = document.createElement("div");
                confetti.className = "confetti";
                confetti.style.left = Math.random() * 100 + "%";
                confetti.style.backgroundColor =
                    colors[Math.floor(Math.random() * colors.length)];
                confetti.style.animationDelay = Math.random() * 0.5 + "s";
                document.body.appendChild(confetti);
                
                setTimeout(() => confetti.remove(), 3500);
            }, i * 30);
        }
    };
    
    // Show error message
    const showError = (message) => {
        // Create error display element if it doesn't exist
        let errorElement = document.getElementById('wheel-error');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.id = 'wheel-error';
            errorElement.className = 'wheel-error';
            document.querySelector('.wheel-section').appendChild(errorElement);
        }
        
        errorElement.textContent = message;
        errorElement.style.display = 'block';
        
        // Hide error after 5 seconds
        setTimeout(() => {
            errorElement.style.display = 'none';
        }, 5000);
    };
    
    // Public API
    return {
        init,
        getSelectedSector: () => selectedSector,
    };
})();

// Initialize when DOM is ready
document.addEventListener("DOMContentLoaded", () => {
    WheelOfFortune.init();
    
    // Initialize Lucide icons if available
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});