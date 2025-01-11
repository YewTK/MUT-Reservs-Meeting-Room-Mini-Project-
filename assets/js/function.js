function updateFullName() {
    var firstName = document.getElementById("fname").value;
    var lastName = document.getElementById("lname").value;
    document.getElementById("fullName").innerHTML = firstName + " " + lastName;
}

