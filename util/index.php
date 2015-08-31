<html>
<head>
<style>
 div {
  margin: 2px 0px 10px 20px;
  width: 700px;
 }
 .content {
  display: none;
 }
</style>
<script type="text/javascript" src="../controller/js/common.js"></script>
<script type="text/javascript" src="../controller/js/jquery-1.8.3.min.js"></script>
</head>
<body>

<?
 error_reporting(E_ALL ^ E_NOTICE);
 ini_set('memory_limit', '700M');
 ini_set('max_execution_time', 1800);
 
 include_once ("../model/legacy.php");
 session_start();
 
 $f = unserialize(file_get_contents('data/rb_project_916609_livetmr.dat'));
 
 foreach ($f->tasklists as $tl) {
  echo "<div><a href='javascript:;' onclick='toggle(".$tl->id.");'>".$tl->id."-".$tl->name."</a><br><div class=content id='".$tl->id."'><hr>";
  if ($tl->tasks) {
   foreach ($tl->tasks as $t) {
    echo "<div>".$t->name."<br>".$t->first_comment->body_html;
    foreach ($t->recent_comments as $c) {
     echo "<div>".$c->body_html."</div>";
    }
    echo "</div><hr>";
   }
  }
  echo "</div></div>";
//  echo "<hr>";
 }
 
 
 echo "<hr>";
 
 ajax_echo_r($f);
 
?>

</body>
</html>