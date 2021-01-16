# trackr: Personal library management, reading/working/todo trackings

## Install

1. Clone the repository
2. ``cd trackr``
3. ``cd docker``
4. ``docker build -t trackr .``
5. ``cd ..``
6. ``docker run --name trackr -p 80:80 -v "$PWD":/var/www/html/trackr -d trackr``
7. ``composer install``
8. ``cd database``
9. ``docker run --name mysql -e MYSQL_ROOT_PASSWORD='password' -v trackr:/var/lib/mysql -p 3306:3306 -v "$PWD":/etc/mysql/conf.d -d mysql:5.7.32``
10. Create database: ``CREATE SCHEMA `trackr` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin ;``
11. Import trackr.sql file into trackr database.
12. Create conf/conf.ini file like below  
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
13. Create logs directory on project root.

### Theme: 
https://usebootstrap.com/theme/tinydash

### Contributing

Please feel free to contribute.

### License

Distributed under the MIT License. See [LICENSE](LICENSE) for more information.