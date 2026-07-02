
<html>
<head>
<?php
session_start();
include("links.php");
?>
<link rel="stylesheet" href="css/search.css">
<script type="text/javascript">
var waecSearchTimer = null;
function SearchItem(forceNow){
  var input = document.getElementById("search_student");
  var holder = document.getElementById("search-student");
  if(!input || !holder){ return; }

  var str = (input.value || "").trim();
  if(str.length === 0){
    holder.innerHTML = "<div class='search-empty-state'><i class='fa fa-search'></i><h3>Start searching</h3><p>Type a student name or index number to load matching records.</p></div>";
    return;
  }
  if(str.length < 2 && !forceNow){
    holder.innerHTML = "<div class='search-note'><i class='fa fa-keyboard-o'></i> Type at least 2 characters...</div>";
    return;
  }

  holder.innerHTML = "<div class='search-loading'><span></span><div><strong>Searching...</strong><p>Checking student records now.</p></div></div>";
  var xhr = new XMLHttpRequest();
  xhr.onreadystatechange = function(){
    if(xhr.readyState === 4){
      if(xhr.status === 200){
        holder.innerHTML = xhr.responseText;
      } else {
        holder.innerHTML = "<div class='search-alert'><i class='fa fa-exclamation-circle'></i> Search failed. Try again.</div>";
      }
    }
  };
  xhr.open("GET", "display-student-items.php?search-item=" + encodeURIComponent(str), true);
  xhr.send();
}

function SearchItemDebounced(){
  if(waecSearchTimer){
    clearTimeout(waecSearchTimer);
  }
  waecSearchTimer = setTimeout(function(){ SearchItem(false); }, 280);
}

function ClearSearchItem(){
  var input = document.getElementById("search_student");
  var holder = document.getElementById("search-student");
  if(input){ input.value = ""; input.focus(); }
  if(holder){ holder.innerHTML = "<div class='search-empty-state'><i class='fa fa-search'></i><h3>Start searching</h3><p>Type a student name or index number to load matching records.</p></div>"; }
}
</script>
</head>
<body>

  <div class="header">
    <!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
  <?php
  include("menu.php");

  ?>    
  <?php
  //include("side-menu.php");

  ?>
  </div>

<?php
//session_start();
if($_SESSION["SYSTEMTYPE"]=="Student")
{
}else{
?>
<div class="main-platform search-page">
  <section class="search-hero">
    <div>
      <span class="search-kicker">Student Finder</span>
      <h1>Search Students</h1>
      <p>Find a student quickly by index number, first name, other names, or surname.</p>
    </div>
    <div class="search-hero-card">
      <i class="fa fa-search"></i>
      <span>Fast Records Lookup</span>
    </div>
  </section>

  <section id="searchbars" class="search-panel">
    <div class="search-panel-heading">
      <span class="search-icon"><i class="fa fa-id-card-o"></i></span>
      <div>
        <h2>Student Search</h2>
        <p>Start typing for live results, or press Enter to refresh immediately.</p>
      </div>
    </div>
    <div class="search-wrap">
      <div class="search-input-wrap">
        <i class="fa fa-search"></i>
        <input class="search-input" type="text" id="search_student" name="search_student" placeholder="Type Index Number / Firstname / Othernames / Surname" oninput="SearchItemDebounced()" onkeydown="if(event.key==='Enter'){ event.preventDefault(); SearchItem(true);}"/>
      </div>
      <button class="button-save search-btn search-btn-primary" type="button" onclick="SearchItem(true)"><i class="fa fa-search"></i> Search</button>
      <button class="search-clear-btn search-btn search-btn-light" type="button" onclick="ClearSearchItem()"><i class="fa fa-times"></i> Clear</button>
    </div>
    <div class="search-note"><i class="fa fa-lightbulb-o"></i> Tip: start with at least 2 characters. Exact index numbers appear first.</div>

    <div id="search-student" name="search-student" class="search-results">
      <div class="search-empty-state"><i class="fa fa-search"></i><h3>Start searching</h3><p>Type a student name or index number to load matching records.</p></div>
    </div>
  </section>
</div>
<?php
}
?>
</body>
</html>
