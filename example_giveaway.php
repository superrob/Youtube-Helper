<?
// Giveaway example using YoutubeHelper
include("youtubehelper.php");
$yh = new youtubeHelper();
$yh->youtubeID = "cBJxnEwjs_A"; // Your video
$yh->youtubeUser = "RobinKaja"; // Your username
$yh->loadComments();
$yh->checkAllSubs(false,false,"");
$comments = $yh->comments;
$winner = $comments[rand(0,count($comments)-1)];
echo "The winner is " . $winner['author'];
?>