# Payme-Checkout-Module-for-Modx-Revolution-ShopKeeper
Payme Checkout Module for Modx (Revolution )+ShopKeeper

## Requirements

- Web Server (Apache suggested)
- Database (MySQLi suggested)
- PHP Version 5.3+
- Merchant ID, Production & Test Keys
- Production & Test Gateway URLs

## Для установки модуля необходимо:

1. Скачать модуль.
2. Создать сниппет с именем «Payme» и вставить  в сниппет  «Payme»  содержимое  из  файла  «SnippetPayme.php»,  который  лежит  в  корне.
3. Импортировать параметры сниппета «Payme» из файла «SnippetParameters.js».
4. Настроить модуль в параметрах сниппета «Payme». (Сниппет «Payme» -Вкладка «Параметры»).
5. Создать таблицы в существующей базе данных по шаблону «PaymeTransactions.sql», файл лежит  в  корне.
6. Добавить в Shopkeeper новый способ оплаты. (Название -«Payme», Значение -«payme»).
7. Прописать [[!Payme&runMode=\`2\`]] в поле «Код шаблона (html)»страницы. Символ "`".
