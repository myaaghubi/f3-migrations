document.getElementById("action_makecase").onclick = function(){
  var input = document.getElementById("makecase_input");
  if (input.checkValidity()) {
    window.location.href = BASE+"/migrations/makecase/"+input.value;
  } else {
    input.classList.add('is-invalid');
  }
  input.classList.add('was-validated');
}