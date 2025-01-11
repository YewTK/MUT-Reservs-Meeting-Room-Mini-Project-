$(document).ready(function () {
  const togglePassword = $("#togglePassword");
  const password = $("#password");
  togglePassword.on("click", function () {
    // Toggle the type attribute
    const type = password.attr("type") === "password" ? "text" : "password";
    password.attr("type", type);
    // Toggle the eye/eye-slash icon class
    togglePassword.toggleClass("bi-eye");
    togglePassword.toggleClass("bi-eye-slash");
  });
});
