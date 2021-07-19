# trackr: Personal library management, reading/working/todo trackings

## Install
1. Clone the repository
2. ``cd trackr/docker``
3. ``docker compose up``
4. ``cd ..``
5. ``composer install``
6.  Create conf/conf.ini file like below  
    ```
    displayErrorDetails = true  
    debug = true  
    
    [db]  
    driver = mysql  
    charset = utf8mb4
    user = test
    password = test
    host = 192.168.2.2
    database = trackr
    ```
7.  Create logs directory on project root.

### Theme: 
https://usebootstrap.com/theme/tinydash

### Contributing
Please feel free to contribute.

### License
Distributed under the MIT License. See [LICENSE](LICENSE) for more information.