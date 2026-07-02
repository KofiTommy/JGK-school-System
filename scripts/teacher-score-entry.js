function scoreEntryFilterAssignments(input){
  var grid = document.getElementById("assignmentGrid");
  if(!grid){ return; }
  var query = (input && input.value ? input.value : "").toLowerCase().trim();
  var cards = grid.querySelectorAll("[data-assignment-label]");
  for(var i = 0; i < cards.length; i++){
    var label = (cards[i].getAttribute("data-assignment-label") || "").toLowerCase();
    cards[i].style.display = (query === "" || label.indexOf(query) !== -1) ? "" : "none";
  }
}

function scoreEntryFilterStudents(input){
  var sheet = input ? input.closest("[data-score-sheet]") : null;
  if(!sheet){ return; }
  var query = (input.value || "").toLowerCase().trim();
  var rows = sheet.querySelectorAll("[data-student-row]");
  for(var i = 0; i < rows.length; i++){
    var label = (rows[i].getAttribute("data-student-label") || "").toLowerCase();
    rows[i].style.display = (query === "" || label.indexOf(query) !== -1) ? "" : "none";
  }
  scoreEntryUpdateSheet(sheet);
}

function scoreEntryVisibleRows(sheet){
  var rows = sheet.querySelectorAll("[data-student-row]");
  var visible = [];
  for(var i = 0; i < rows.length; i++){
    if(rows[i].style.display !== "none"){
      visible.push(rows[i]);
    }
  }
  return visible;
}

function scoreEntryTotalInput(sheet){
  return sheet.querySelector("[data-role='total-score']");
}

function scoreEntryUpdateMarkState(input, totalInput){
  var row = input.closest("[data-student-row]");
  if(!row){ return; }
  var value = (input.value || "").trim();
  var total = totalInput ? (totalInput.value || "").trim() : "";
  var isInvalid = false;

  if(value !== ""){
    var numericValue = Number(value);
    if(isNaN(numericValue) || numericValue < 0){
      isInvalid = true;
    }else if(total !== "" && !isNaN(Number(total)) && numericValue > Number(total)){
      isInvalid = true;
    }
  }

  input.classList.toggle("is-invalid", isInvalid);
  row.classList.toggle("is-invalid", isInvalid);
}

function scoreEntryUpdateSheet(sheet){
  var rows = sheet.querySelectorAll("[data-student-row]");
  var visibleRows = scoreEntryVisibleRows(sheet);
  var totalVisible = visibleRows.length;
  var selectedVisible = 0;
  var enteredVisible = 0;
  var invalidVisible = 0;
  var totalInput = scoreEntryTotalInput(sheet);

  for(var i = 0; i < rows.length; i++){
    var checkbox = rows[i].querySelector("[data-role='student-checkbox']");
    var markInput = rows[i].querySelector("[data-role='student-mark']");
    rows[i].classList.toggle("is-selected", !!(checkbox && checkbox.checked));

    if(markInput){
      scoreEntryUpdateMarkState(markInput, totalInput);
    }
  }

  for(var j = 0; j < visibleRows.length; j++){
    var checkboxVisible = visibleRows[j].querySelector("[data-role='student-checkbox']");
    var markVisible = visibleRows[j].querySelector("[data-role='student-mark']");
    if(checkboxVisible && checkboxVisible.checked){
      selectedVisible++;
    }
    if(markVisible && (markVisible.value || "").trim() !== ""){
      enteredVisible++;
    }
    if(markVisible && markVisible.classList.contains("is-invalid")){
      invalidVisible++;
    }
  }

  var selectedCount = sheet.querySelector("[data-role='selected-count']");
  var enteredCount = sheet.querySelector("[data-role='entered-count']");
  var visibleCount = sheet.querySelector("[data-role='visible-count']");
  var invalidCount = sheet.querySelector("[data-role='invalid-count']");
  var validationNote = sheet.querySelector("[data-role='validation-note']");
  var saveButton = sheet.querySelector("[data-role='save-button']");
  var masterCheckbox = sheet.querySelector("[data-role='master-checkbox']");

  if(selectedCount){ selectedCount.textContent = String(selectedVisible); }
  if(enteredCount){ enteredCount.textContent = String(enteredVisible); }
  if(visibleCount){ visibleCount.textContent = String(totalVisible); }
  if(invalidCount){ invalidCount.textContent = String(invalidVisible); }

  if(validationNote){
    validationNote.classList.toggle("is-warning", invalidVisible > 0);
    if(invalidVisible > 0){
      validationNote.textContent = invalidVisible + " mark(s) need attention before saving.";
    }else{
      validationNote.textContent = "Typing in a mark auto-selects the student row for saving.";
    }
  }

  if(masterCheckbox){
    masterCheckbox.checked = totalVisible > 0 && selectedVisible === totalVisible;
    masterCheckbox.indeterminate = selectedVisible > 0 && selectedVisible < totalVisible;
  }

  if(saveButton){
    var totalValue = totalInput ? (totalInput.value || "").trim() : "";
    var totalReady = totalValue !== "" && !isNaN(Number(totalValue)) && Number(totalValue) >= 0;
    saveButton.disabled = !totalReady || selectedVisible === 0 || invalidVisible > 0;
  }
}

function scoreEntryToggleVisible(sheet, checked){
  var rows = scoreEntryVisibleRows(sheet);
  for(var i = 0; i < rows.length; i++){
    var checkbox = rows[i].querySelector("[data-role='student-checkbox']");
    if(checkbox){
      checkbox.checked = checked;
    }
  }
  scoreEntryUpdateSheet(sheet);
}

function scoreEntryInitSheet(sheet){
  if(!sheet){ return; }

  var totalInput = scoreEntryTotalInput(sheet);
  var studentSearch = sheet.querySelector("[data-role='student-search']");
  var selectVisibleButton = sheet.querySelector("[data-role='select-visible']");
  var clearVisibleButton = sheet.querySelector("[data-role='clear-visible']");
  var masterCheckbox = sheet.querySelector("[data-role='master-checkbox']");
  var markInputs = sheet.querySelectorAll("[data-role='student-mark']");
  var checkboxes = sheet.querySelectorAll("[data-role='student-checkbox']");

  if(studentSearch){
    studentSearch.addEventListener("input", function(){
      scoreEntryFilterStudents(studentSearch);
    });
  }

  if(totalInput){
    totalInput.addEventListener("input", function(){
      scoreEntryUpdateSheet(sheet);
    });
  }

  if(selectVisibleButton){
    selectVisibleButton.addEventListener("click", function(){
      scoreEntryToggleVisible(sheet, true);
    });
  }

  if(clearVisibleButton){
    clearVisibleButton.addEventListener("click", function(){
      scoreEntryToggleVisible(sheet, false);
    });
  }

  if(masterCheckbox){
    masterCheckbox.addEventListener("change", function(){
      scoreEntryToggleVisible(sheet, masterCheckbox.checked);
    });
  }

  for(var i = 0; i < markInputs.length; i++){
    markInputs[i].addEventListener("input", function(){
      var row = this.closest("[data-student-row]");
      if(row){
        var checkbox = row.querySelector("[data-role='student-checkbox']");
        if(checkbox){
          checkbox.checked = (this.value || "").trim() !== "";
        }
      }
      scoreEntryUpdateSheet(sheet);
    });
  }

  for(var j = 0; j < checkboxes.length; j++){
    checkboxes[j].addEventListener("change", function(){
      scoreEntryUpdateSheet(sheet);
    });
  }

  scoreEntryUpdateSheet(sheet);
}

document.addEventListener("DOMContentLoaded", function(){
  var assignmentSearch = document.getElementById("assignmentSearch");
  if(assignmentSearch){
    assignmentSearch.addEventListener("input", function(){
      scoreEntryFilterAssignments(assignmentSearch);
    });
  }

  var sheets = document.querySelectorAll("[data-score-sheet]");
  for(var i = 0; i < sheets.length; i++){
    scoreEntryInitSheet(sheets[i]);
  }
});
