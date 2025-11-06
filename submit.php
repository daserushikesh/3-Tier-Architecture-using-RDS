<?php
$servername = "--";  // Database host(endpoint>
$username = "--";         // Database username
$password = "--";             // Database password
$dbname = "--";      // Database name
// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
// Collect form data
$fullname = $_POST['fullname'];
$email    = $_POST['email'];
$phone    = $_POST['phone'];
$course   = $_POST['course'];
// Insert into database
$sql = "INSERT INTO students (fullname, email, phone, course)
        VALUES ('$fullname', '$email', '$phone', '$course')";
if ($conn->query($sql) === TRUE) {
  echo "<h2>Registration successful!</h2>";
  echo "<a href='form.html'>Go Back</a>";
} else {
  echo "Error: " . $sql . "<br>" . $conn->error;
}
$conn->close();
?>