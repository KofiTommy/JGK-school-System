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

  var uploadForms = document.querySelectorAll(".score-upload-form");
  for(var j = 0; j < uploadForms.length; j++){
    uploadForms[j].addEventListener("submit", function(event){
      var message = this.getAttribute("data-confirm-message") || "Please check this score sheet carefully before uploading. Once the result is approved, you will not be able to change the scores unless the administrator reopens corrections. Do you want to continue?";
      if(message && !window.confirm(message)){
        event.preventDefault();
      }
    });
  }
});
