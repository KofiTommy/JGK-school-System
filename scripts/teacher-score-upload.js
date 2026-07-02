document.addEventListener("DOMContentLoaded", function(){
  var fileInputs = document.querySelectorAll("[data-upload-file-input]");
  for(var i = 0; i < fileInputs.length; i++){
    fileInputs[i].addEventListener("change", function(){
      var picker = this.closest(".score-upload-file-picker");
      if(!picker){ return; }
      var label = picker.querySelector("[data-upload-file-name]");
      if(!label){ return; }
      if(this.files && this.files.length > 0){
        label.textContent = this.files[0].name;
      }else{
        label.textContent = "Select an Excel file";
      }
    });
  }
});
