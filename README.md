# trackr: Personal library management, reading/working/todo trackings

## Install

1. Clone the repository
2. ``cd trackr``
3. ``composer install``
4. ``cd docker``
5. ``docker build -t trackr .``
6. ``cd ..``
7. ``docker run --name trackr -p 80:80 -v "$PWD":/var/www/html/trackr -d trackr``
8. ``docker run --name mysql -e MYSQL_ROOT_PASSWORD='password' -p 3306:3306 -v trackr:/var/lib/mysql -d mysql:5.7``
9. Create database: ``CREATE SCHEMA trackr DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;``
10. Import trackr.sql file into trackr database.
11. Create conf/conf.ini file like below  
    ```
    displayErrorDetails = true  
    debug = true  
    
    [db]  
    driver = mysql  
    charset = utf8mb4
    user = test
    password = test
    host = 172.17.0.1
    database = trackr
    ```
12. create logs directory on project root.

### Theme: https://usebootstrap.com/theme/tinydash