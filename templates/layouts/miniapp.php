<?php
/**
 * @var \Psr\Http\Message\ServerRequestInterface $request
 * @var string $content
 */
?>
<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Колесо Фортуны</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Playfair+Display&family=Orbitron&family=Bebas+Neue&display=swap"
        rel="stylesheet"/>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#667eea',
                        secondary: '#764ba2',
                        accent: '#e74c3c',
                        highlight: '#f6d365',
                        success: '#27ae60',
                        info: '#3498db',
                        warning: '#fda085',
                        danger: '#e74c3c',
                        grayLight: '#ecf0f1',
                        gray: '#bdc3c7',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['Playfair Display', 'serif'],
                        accent: ['Orbitron', 'sans-serif'],
                        button: ['Bebas Neue', 'sans-serif'],
                    },
                    boxShadow: {
                        wheel: '0 10px 30px rgba(0, 0, 0, 0.3)',
                        'button-hover': '0 8px 25px rgba(0, 0, 0, 0.3)',
                    },
                    borderRadius: {
                        'wheel-btn': '60px',
                    },
                },
            },
        };
    </script>

    <!-- Styles -->

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="h-full bg-gradient-to-br from-primary to-secondary flex flex-col items-center justify-center p-4 text-white font-sans">

<?= $content ?>
</body>
</html>