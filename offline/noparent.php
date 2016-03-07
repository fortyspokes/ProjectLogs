<?php //copyright 2014 C.D.Price
/* Any page under the document root (except the index page, of course) should include this code as the first thing.  The following javascript should also be included.
if (top == self) {
  top.location = "https://<?php echo($_SERVER["HTTP_HOST"]); ?>";
}
*/
session_start();
if(!isset($_SESSION['_SITE_CONF'])){
    header("Location: https://".$_SERVER["HTTP_REFERER"]);
    exit;
}
?>

