<?php

namespace App\enum;

enum JobTypes: string
{
    case GET_PARENT_BOOKMARK_TITLE = 'get_parent_bookmark_title';
    case GET_CHILD_BOOKMARK_TITLE = 'get_child_bookmark_title';
    case SCRAPE_BOOK_ON_IDEFIX = 'scrape_book_on_idefix';
    case GET_KEYWORD_ABOUT_BOOKMARK = 'get_keyword_about_bookmark';

}