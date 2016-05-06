В composer.json добавляем в блок require
```json
 "vis/registration_l5" : "1.*"
```
Выполняем
```json
composer update
```
Добавляем в файле app.php в блок providers
```php
  Vis\Registration\RegistrationServiceProvider::class,
```
Публикуем конфиги и js файлы
```json
php artisan vendor:publish --tag=registration --force
```
Публикуем если нужно views файлы
```json
php artisan vendor:publish --tag=registration_views --force
```
Для отображения форм использовать
```php
    @include("registration::registration_form")
    @include("registration::authorization_form")
    @include("registration::forgot_pass_form")
```