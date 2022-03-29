# trackr

### Personal library management, reading/working/todo trackings

## Install
1. Clone the repository
2. ``cd trackr``
3. ``docker compose up``
4. ``composer install``
5. Create .env file like below in project root. 

```
displayErrorDetails=1
debug=1
MYSQL_USER=root
MYSQL_PASSWORD=password
MYSQL_DATABASE=trackr
MYSQL_HOST=192.168.2.2
```

6. Create logs directory on project root.

### Appendix
#### Backup:
```shell
# Add to cron:
# Local
docker exec mysql sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" trackr' | bzip2 > ~/trackr/backups/`date +\%d-\%m-\%Y`.sql.bz2
# iCloud folder
docker exec mysql sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" trackr' | bzip2 > ~/Library/Mobile\ Documents/com\~apple\~CloudDocs/trackr.sql.bz2
```
#### Versioning Backup
```shell
# create a git repository named "trackr-backups" and add commands below into a shell script and run it with cron
cd trackr-backups
docker exec mysql sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" trackr' > ./trackr-backup.sql
git add .
git commit -m "`date +\%Y-\%m-\%d`"
```

### Themes and Used Libraries
- Theme: https://usebootstrap.com/theme/tinydash
-  Dark Theme: https://github.com/xcatliu/simplemde-theme-dark
- Simple MDE: https://github.com/sparksuite/simplemde-markdown-editor

### Contributing
Please feel free to contribute.

### License
Distributed under the MIT License. See [LICENSE](LICENSE) for more information.