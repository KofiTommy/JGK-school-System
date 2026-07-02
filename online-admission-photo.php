<?php
$filename = isset($_GET["file"]) ? basename(trim((string)$_GET["file"])) : "";
$uploadsDir = __DIR__.DIRECTORY_SEPARATOR."uploads".DIRECTORY_SEPARATOR;

if($filename !== ""){
    $path = $uploadsDir.$filename;
    if(is_file($path)){
        $mime = "";
        if(function_exists("finfo_open")){
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if($finfo){
                $mime = (string)@finfo_file($finfo, $path);
                finfo_close($finfo);
            }
        }

        if($mime === ""){
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if($ext === "jpg" || $ext === "jpeg"){
                $mime = "image/jpeg";
            }elseif($ext === "png"){
                $mime = "image/png";
            }elseif($ext === "gif"){
                $mime = "image/gif";
            }elseif($ext === "webp"){
                $mime = "image/webp";
            }else{
                $mime = "application/octet-stream";
            }
        }

        header("Content-Type: ".$mime);
        header("Content-Length: ".filesize($path));
        header("Cache-Control: public, max-age=86400");
        readfile($path);
        exit();
    }
}

$svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="640" height="640" viewBox="0 0 640 640" role="img" aria-label="No admission photo uploaded">
  <defs>
    <linearGradient id="bg" x1="0%" x2="100%" y1="0%" y2="100%">
      <stop offset="0%" stop-color="#edf4fa"/>
      <stop offset="100%" stop-color="#d7e4f2"/>
    </linearGradient>
  </defs>
  <rect width="640" height="640" rx="42" fill="url(#bg)"/>
  <circle cx="320" cy="235" r="96" fill="#9bb3c7"/>
  <path d="M160 520c22-94 90-142 160-142s138 48 160 142" fill="#9bb3c7"/>
  <text x="320" y="590" text-anchor="middle" font-family="Arial, sans-serif" font-size="30" fill="#36536b">No Photo Uploaded</text>
</svg>
SVG;

header("Content-Type: image/svg+xml; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
echo $svg;
