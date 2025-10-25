<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подтверждение выхода</title>
    <!-- Подключение Tailwind CSS для стилей -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Установка шрифта Inter */
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }
    </style>
    <script>
        // Конфигурация Tailwind
        tailwind.config = {
            darkMode: 'media', // Автоматическое переключение на основе настроек системы
            theme: {
                extend: {
                    colors: {
                        'primary': '#1d4ed8', /* blue-700 */
                        'primary-hover': '#1e40af', /* blue-800 */
                        // Цвета для светлой темы
                        'gray-bg': '#f3f4f6', /* gray-100 */
                        // Цвета для темной темы
                        'dark-bg': '#1f2937', /* gray-900 */
                        'dark-card': '#374151', /* gray-700 */
                    }
                }
            }
        }
        
        // Функция для возврата назад (например, на главную страницу)
        function goBack() {
            window.history.back();
        }
    </script>
</head>
<!-- Адаптивный фон: светлый (bg-gray-bg) и темный (dark:bg-dark-bg) -->
<body class="bg-gray-bg dark:bg-dark-bg flex items-center justify-center p-4 transition-colors duration-300">

    <!-- Адаптивная карточка: светлая (bg-white, shadow-2xl) и темная (dark:bg-gray-800, dark:shadow-xl) -->
    <div class="max-w-md w-full bg-white dark:bg-gray-800 rounded-xl shadow-2xl dark:shadow-none p-6 md:p-8 transform transition duration-500">
        <div class="text-center">
            <!-- Иконка: остается желтой для привлечения внимания -->
            <svg class="mx-auto h-12 w-12 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.332 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <!-- Заголовок: светлый (text-gray-900) и темный (dark:text-white) -->
            <h3 class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">Вы действительно хотите выйти?</h3>
            <!-- Описание: светлое (text-gray-500) и темное (dark:text-gray-300) -->
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-300">
                Ваша текущая сессия будет завершена. Вы сможете войти в систему снова в любое время.
            </p>
        </div>
        <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
            <!-- Кнопка "Выйти" (Основная, синяя) -->
            <a href="/components/php/logout.php" 
               class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-3 bg-primary text-base font-medium text-white hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary dark:focus:ring-offset-gray-800 sm:col-start-2 transition ease-in-out duration-150 mb-3 sm:mb-0">
                Да, я хочу выйти
            </a>

            <!-- Кнопка "Отмена" (Второстепенная, адаптивная) -->
            <button type="button" 
                    onclick="goBack()" 
                    class="w-full inline-flex justify-center rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-3 
                           bg-white dark:bg-gray-700 
                           text-gray-700 dark:text-gray-200 
                           hover:bg-gray-50 dark:hover:bg-gray-600 
                           focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800 sm:col-start-1 transition ease-in-out duration-150">
                Отмена
            </button>
        </div>
    </div>
</body>
<noscript>
    <meta http-equiv="refresh" content="0; url=/components/pages/js.php">
</noscript>
</html>
