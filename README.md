# Payme-Checkout-Module-for-Modx-Evolution-ShopKeeper
Payme Checkout Module for Modx (Evolution )+ShopKeeper

## Requirements

- Web Server (Apache suggested)
- Database (MySQLi suggested)
- PHP Version 5.3+
- Merchant ID, Production & Test Keys
- Production & Test Gateway URLs

## Для установки модуля необходимо:

1. Скопировать папку payme из каталога sourse в папку assets/snippets/. 

2. Создать новый сниппет.
	Для этого в системе управления перейти "Элементы" -> "Управление элементами" -> "Сниппеты", нажать "Новый сниппет". 

   - Название сниппета: Payme,
   - Описание: Оплата через Payme.
   
   Открыть файл snippet.txt, cкопировать содержимое и вставить в поле "Код сниппета". Нажать кнопку "Сохранить". 

4. Проверьте чтобы на странице оформления заказа в вызове сниппета eForm был указан параметр &gotoid.
   Пример:

   [!eForm? &gotoid=`15` ..... !] здесь 15 - это ID страницы, которая будет открываться после отправки заказа.
   
   Открыть для редактирования шаблона, которая открывается ПОСЛЕ оформления заказа (&gotoid в eForm). 
   Вставить в поле "Код шаблона" вызов сниппета:

   [!Payme!]

   Сохранить. 

5. Добавьте способ оплаты в шаблон оформления заказа.
   Пример:
 
   <select name="payment" >
      <option value="payme">Payme</option>
      <option value="При получении">При получении</option> 
      <option value="other">Другая платежная система</option>
   </select> 

6.  Изменить конфигурацию Payme (файл /assets/snippets/payme/paymeConfig.php):
	"Элементы" -> "Управление элементами" -> "Сниппеты", нажать "Payme", перейти на вкладку "Свойства"
    Открыть файл SnippetParameters.js, cкопировать содержимое и вставить. Нажать "Обновить параметры". 
	Перейти на вкладку "Конфигурация",настроить.
	
 7. Создать таблицы в существующей базе данных по шаблону «PaymeTransactions.sql», файл лежит в корне.
  
 
 Ваш Endpoint URL :
 
 http://имя_вашего_сайта/assets/snippets/payme/result.php 

 
