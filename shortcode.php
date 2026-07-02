<?php
@$code_2 = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"),0,5);
@$code =date("m").date("s");
@$shortcode = $code.$code_2;
?>