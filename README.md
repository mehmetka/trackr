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

TRACKR_BASE_URL=http://localhost

MYSQL_USER=root
MYSQL_PASSWORD=strong-password
MYSQL_DATABASE=trackr
MYSQL_HOST=192.168.2.2

RABBITMQ_HOST=192.168.2.4
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/

OPENAI_CHATGPT_API_KEY=xyz
```

### Themes and Used Libraries
- Theme: https://usebootstrap.com/theme/tinydash
- Simple MDE Dark Theme: https://github.com/xcatliu/simplemde-theme-dark
- Simple MDE: https://github.com/sparksuite/simplemde-markdown-editor

### Contributing
Please feel free to contribute.

### License
Distributed under the MIT License. See [LICENSE](LICENSE) for more information.