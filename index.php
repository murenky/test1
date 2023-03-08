<?php

include 'MailingList.php';

$ml = new MailingList();

$ml->loadUsers('users.csv');

$ml->addMailingList('hello', 'Hello, %name%');

$ml->startMailingList('hello');
