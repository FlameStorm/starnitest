# README #

Решение тестового задания компании StarniGames

# Постановка задачи #

#### Условия

https://telegra.ph/Testovoe-zadanie-PHP-programmist-fullstack-05-18

Задача написать скрипт, который слушает блокчейн-сеть и выводит события в браузере через WebSockets.

Задача другими словами: PHP-скриптом подключиться к одному WS-серверу, слушать данные, записывать их в локальное хранилище. Поднять свой WS сервер и транслировать данные из локального хранилища. На клиенте отрисовывать полученные данные.


### Как развернуть проект? ###

1. Для разворачивания проекта необходимо клонировать или скачать репозиторий и разместить его в директории доступной для обработки вашим веб-сервером, аналогично разворачиванию любого небольшого сайта.

2. Создать базу, пользователя под новую базу и импортировать структуру базы из подпапки `.install` - там находится соотв. дамп формата MySQL (MariaDB).
Реквизиты (имя базы, пользователя, пароль) должны соответствовать указанным в соотв разделе файла `config.php`

3. Разрешить на файрволе сервера подключение к порту 2020

4. Требования к серверу - MySQL 5.7+ / MariaDB 10.2, PHP 7.0+, Apache 2.X


### Запуск ###

Для функционирования системы необходима фоновая (daemon) работа друх компонентов.

Первое, это сервер socket.io соединений `server.php` (в корне проекта). Запуск сервера:

`php server.php start`

Второе, это клиент `client.php` получения блокчейн данных по API предоставляему серверами Infura. Запуск клиента:

`php client.php`

### Ссылка на лайв решение ###

http://2291.ru/tmp/starnitest/

(https версия с 2021 портом wss вебсокет-подключения может быть реализована позднее)

