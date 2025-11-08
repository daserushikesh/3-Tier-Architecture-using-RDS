# AWS 3-Tier Architecture with RDS: Hands-On Practice Guide
**Purpose**  
Step-by-step hands-on walkthrough to build a secure 3‑tier web application on AWS using a LEMP stack (Linux, Nginx, MySQL, PHP) with Amazon RDS (MySQL) as the data layer. This guide follows the exact steps you provided and converts them into a professional, GitHub-ready README with commands and code blocks for copy-paste.

---

## Architecture Summary
- **Web Tier (Public)** — Nginx running on EC2 in a public subnet (serves `form.html`).  
- **App Tier (Private)** — EC2 in a private subnet running PHP / PHP‑FPM and hosting `submit.php`.  
- **DB Tier (Private)** — Amazon RDS (MySQL) in a DB subnet group (private, not publicly accessible).  
- **Networking** — Custom VPC, Internet Gateway (IGW), NAT Gateway (for private subnet egress), Route Tables, Security Groups, Elastic IP (for NAT).
---

## 🧭 Overview

In this hands-on lab you will design and deploy a **3-Tier Web Application Architecture** on Amazon Web Services (AWS). The stack includes:
- VPC for networking and isolation
- EC2 instances for Web and Application tiers
- RDS (MySQL/Aurora) for the Database tier
- NAT Gateway, Internet Gateway, and Route Tables for connectivity management
By the end of this practice, you’ll have a fully functional environment that mimics a real-world production setup complete with public/private subnets, secure EC2 communication, and database connectivity using RDS.

This guide is instructor-style: each step tells you what you will do and why. It preserves the full commands, scripts, and configuration blocks so you can copy-paste during the lab.

> **Warning:** NAT Gateways and RDS instances can incur charges. Clean up resources when finished (see Cleanup section).

---

## ⚙️ Prerequisites

Before starting, ensure you have:

- An active **AWS account** with permissions to create VPC, EC2, Elastic IP, NAT Gateway, and RDS.
- A chosen **AWS region** (e.g., `us-east-1`).
- An SSH key pair (`.pem`) for EC2 access.
- Local tools: `ssh`, `scp` (PowerShell on Windows supports scp).
- Basic familiarity with AWS Console and Linux commands.

---

## Table of Contents

1. VPC & Networking (Steps 1–6)
2. EC2: Web & App servers (Steps 7–13)
3. RDS: Subnet group and database (Steps 14–17)
4. Application files: HTML, PHP, nginx blocks
5. Verification & Troubleshooting
6. Cleanup & Cost controls
7. Author

---

# Part 1 — VPC & Networking

## Step 1 — Create a custom VPC

In this step, you will create a custom VPC to host the 3-tier architecture.

1. Open AWS Console → **VPC** → **Create VPC**.
2. Provide:
   - **Name tag:** `Architecture`
   - **IPv4 CIDR block:** `10.0.0.0/16`
3. Click **Create VPC**.

> Note: A custom VPC is private by default (no internet gateway attached). A route table is created automatically.

---

## Step 2 — Route Table & Internet Gateway

You will rename the initial route table and create/attach an Internet Gateway (IGW).

1. In **VPC → Route Tables**, find the route table for your VPC and rename it to `private-rt`.
2. In **VPC → Internet Gateways**:
   - Click **Create Internet Gateway**
   - Name: `Internet-Gateway-For-RDS`
   - Click **Create**
   - Select it → **Actions → Attach to VPC** → choose `Architecture`

---

## Step 3 — Create Subnets

You will create one public (Web) subnet and one private (App) subnet.

1. **Subnets → Create subnet**
2. Add the following:

- **Web Subnet**
  - Name: `web-subnet`
  - AZ: choose one (e.g., `us-east-1a`)
  - CIDR: `10.0.1.0/16`

- **App Subnet**
  - Name: `app-subnet`
  - AZ: choose a different one (e.g., `us-east-1b`)
  - CIDR: `10.0.2.0/16`

Click **Create subnet**.

> Tip: In production labs you would typically use /24 ranges; the script uses /16 for clarity.

---

## Step 4 — Make public route table & associate web subnet

1. **Route Tables → Create route table**
   - Name: `public-rt`
   - VPC: `Architecture`
2. Select `public-rt` → **Routes → Edit routes → Add route**
   - Destination: `0.0.0.0/0`
   - Target: **Internet Gateway** (the IGW you created)
   - Save
3. Select `public-rt` → **Subnet associations → Edit subnet associations**
   - Select `web-subnet`
   - Save

`web-subnet` is now public (instances launched with auto-assign public IP will receive a public IP).

---

## Step 5 — Create a NAT Gateway (for private outbound internet)

1. **NAT Gateways → Create NAT Gateway**
   - Name: `Nat-gateway-for-rds`
   - Subnet: choose **web-subnet** (public)
   - Elastic IP allocation: **Allocate Elastic IP**
   - Create NAT Gateway

> Important: NAT Gateway must be in a public subnet. NAT Gateway incurs costs; delete when done.

---

## Step 6 — Update private route table to use NAT and associate app subnet

1. In **Route Tables**, select `private-rt`.
2. **Routes → Edit routes → Add route**
   - Destination: `0.0.0.0/0`
   - Target: NAT Gateway you just created
   - Save
3. **Subnet associations → Edit subnet associations**
   - Select `app-subnet`
   - Save
   - 
     ### Route Table Configuration Summary

| Route Table Name | Type       | Associated Subnet(s) | Destination (0.0.0.0/0) | Target            | Internet Access |
|------------------|------------|----------------------|--------------------------|-------------------|-----------------|
| **Public RT**    | Public     | Web Subnet           | 0.0.0.0/0               | Internet Gateway (igw) | ✅ Yes |
| **Private RT**   | Private    | App Subnet (Private) | 0.0.0.0/0               | NAT Gateway (nat)      | ⚙️ Via NAT |


Verification: `public-rt` should show IGW route; `private-rt` should show NAT gateway route.

---

# Part 2 — EC2 Setup (Web & App Servers)

## Step 7 — Launch EC2 Instances

### i) Web Server (Public)

In this step, you will launch the Web server in the public subnet and install Nginx via user data.

1. EC2 → Launch Instance
2. Settings:
   - **Name:** `Web-server`
   - **AMI:** Ubuntu LTS
   - **Instance type:** t2.micro
   - **Key pair:** choose or create
   - **Network settings:** VPC `Architecture`, Subnet `web-subnet`, Auto-assign Public IP: **Enable**
3. Security group: create `web-sg` with inbound rules:
   - HTTP — TCP 80 — Source `0.0.0.0/0`
   - SSH — TCP 22 — Source `Your IP`
4. Advanced details → User data:

```bash
#!/bin/bash
sudo apt update 
sudo apt install nginx -y
sudo service nginx start
sudo systemctl enable nginx
```

5. Launch instance.

> Result: Web server will be accessible via public IP.

---

### ii) App Server (Private)

Now launch the App server in the private subnet and install Nginx, PHP, and MariaDB client via user data.

1. EC2 → Launch Instance
2. Settings:
   - **Name:** `App-server`
   - **AMI:** Ubuntu LTS
   - **Instance type:** t2.micro
   - **Key pair:** same or new
   - **Network settings:** VPC `Architecture`, Subnet `app-subnet`, Auto-assign Public IP: **Disable**
3. Security group: create `app-sg` (we will add inbound rule from `web-sg` later)
4. Advanced details → User data:

```bash
#!/bin/bash
sudo apt update 
sudo apt install nginx php8.3 php8.3-fpm php8.3-mysql mariadb-server -y 
sudo service nginx start
sudo service php8.3-fpm start
```

5. Launch instance.

> Note: App server uses the NAT Gateway for outbound internet access (package updates).

---

## Step 8 — Configure Security Groups for Inter-tier Communication

You must allow Web server to reach App server via HTTP.

1. Copy `web-sg` Security Group ID (e.g., `sg-xxxxxxxx`).
2. Go to App Server → **Security → Security Groups** → `app-sg` → **Edit inbound rules**
3. Add:
   - Type: HTTP
   - Protocol: TCP
   - Port: 80
   - Source: Custom → paste `web-sg` Security Group ID
4. Save rules.

**Security Group Summary**

- **web-sg**
  - HTTP 80 — 0.0.0.0/0
  - SSH 22 — Your IP

- **app-sg**
  - HTTP 80 — Source: `web-sg` (security group reference)

---

## Step 9 — Transfer PEM and SSH into Web Server

From your local machine (PowerShell example on Windows):

```powershell
scp -i .\path	o\your-key.pem .\path	o\your-key.pem ubuntu@<WEB_PUBLIC_IP>:.
```

Example:

```powershell
scp -i .\Desktop\Aws\public-server.pem .\Desktop\Aws\public-server.pem ubuntu@98.88.252.41:.
```

SSH into the Web server:

```powershell
ssh -i .\path	o\your-key.pem ubuntu@<WEB_PUBLIC_IP>
# Example:
# ssh -i .\Desktop\Aws\public-server.pem ubuntu@98.88.252.41
```

---

## Step 10 — Verify Nginx & Create `form.html` on Web Server

1. Verify Nginx:

```bash
sudo service nginx status
# if not running:
sudo apt update
sudo apt install nginx -y
sudo service nginx start
sudo systemctl enable nginx
```

2. Create `/var/www/html/form.html`:

```bash
sudo nano /var/www/html/form.html
```

Paste the following HTML (full file):

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Registration Form</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f4f7f8;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }
    .container {
      background: #fff;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 5px 10px rgba(0,0,0,0.1);
      width: 400px;
    }
    h2 {
      text-align: center;
      margin-bottom: 20px;
    }
    label {
      display: block;
      margin-top: 10px;
      font-weight: bold;
    }
    input, select {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      border-radius: 5px;
      border: 1px solid #ccc;
    }
    button {
      width: 100%;
      padding: 12px;
      background: #28a745;
      color: white;
      border: none;
      border-radius: 5px;
      margin-top: 15px;
      cursor: pointer;
      font-size: 16px;
    }
    button:hover {
      background: #218838;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Student Registration</h2>
    <form action="submit.php" method="post">
      <label for="fullname">Full Name</label>
      <input type="text" name="fullname" required>

      <label for="email">Email</label>
      <input type="email" name="email" required>

      <label for="phone">Phone</label>
      <input type="text" name="phone" required>

      <label for="course">Course</label>
      <select name="course" required>
        <option value="">-- Select Course --</option>
        <option value="AWS">AWS</option>
        <option value="DevOps">DevOps</option>
        <option value="Python">Python</option>
        <option value="Data Science">Data Science</option>
      </select>

      <button type="submit">Register</button>
    </form>
  </div>
</body>
</html>
```
Save and exit (`Ctrl+O`, `Ctrl+X`).
> Reminder: This code of form.html and submit.php is for learning purposes. Use prepared statements in real apps.

3. Test in browser:

```
http://<WEB_PUBLIC_IP>/form.html
```

If you see the form, Nginx served it correctly.

---

## Step 11 — Configure Nginx on Web Server to Proxy PHP Requests to App Server

Edit Nginx default site on **Web Server**:

```bash
sudo nano /etc/nginx/sites-enabled/default
```

Locate or add a PHP handling block and replace with:

```nginx
# pass PHP scripts to App server (proxy)
location ~ \.php$ {
    proxy_pass http://10.0.2.49; 
    #       include snippets/fastcgi-php.conf;
    #
    #       # With php-fpm (or other unix sockets):
    #       fastcgi_pass unix:/run/php/php7.4-fpm.sock;
    #       # With php-cgi (or other tcp sockets):
    #       fastcgi_pass 127.0.0.1:9000;
}
```

> Replace `10.0.2.49` with your App Server private IP (example: `10.0.2.240`).

Save and reload nginx:

```bash
sudo service nginx restart
sudo service nginx reload
```

---

# Part 3 — App Server & RDS Integration

## Step 12 — SSH into App Server via Web Server

Since App Server is private, SSH into it from the Web Server:

1. SSH to Web Server from your local machine:
```bash
ssh -i path/to/key.pem ubuntu@<WEB_PUBLIC_IP>
```

2. From the Web Server, SSH into the App Server:
```bash
ssh -i public-server.pem ubuntu@<AppServerPrivateIP>
```

You are now on the App Server.

---

## Step 13 — Verify services and PHP on App Server

Check services and versions:

```bash
php -v
nginx -v
sudo service nginx status
sudo service php8.3-fpm status
```

If packages are missing, install:

```bash
sudo apt update
sudo apt install -y nginx php8.3 php8.3-fpm php8.3-mysql mariadb-server
sudo service nginx start
sudo service php8.3-fpm start
```

---

## Step 14 — Configure App Server Nginx for PHP-FPM

Edit the default site:

```bash
sudo nano /etc/nginx/sites-enabled/default
```

Add or update the PHP block:

```nginx
# pass PHP scripts to FastCGI server
location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    #
    #       # With php-fpm (or other unix sockets):
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    #       # With php-cgi (or other tcp sockets):
    #       fastcgi_pass 127.0.0.1:9000;
}
```

Save and reload:

```bash
sudo service nginx restart
sudo service nginx reload
sudo service php8.3-fpm restart
sudo service php8.3-fpm reload
```

---

## Step 15 — Create test.php and submit.php on App Server

Create `test.php` to verify PHP is working:

```bash
sudo nano /var/www/html/test.php
```

Paste:

```php
<?php
    echo "Tested okey!";
?>
```

Save and exit.

Visit: `http://<WEB_PUBLIC_IP>/test.php` (web server proxies to app server). You should see `Tested okey!`.

---

### Create `submit.php`

Create `submit.php` on the App Server (or ensure web server proxies to it):

```bash
sudo nano /var/www/html/submit.php
```

Paste the following (as from original script):

```php
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
```
Save and exit.
> Reminder: This code of form.html and submit.php is for learning purposes. Use prepared statements in real apps.
> **Security note:** This script uses direct interpolation of `$_POST` variables into SQL it's fine for lab practice but **not** safe for production. Use prepared statements in real applications.

---

## Step 16 — Create RDS Subnet Group

1. In the AWS Console, go to **RDS → Subnet groups** → **Create DB subnet group**
2. Provide:
   - Name: `db-subnet-group`
   - Description: `Subnet group for 3-tier lab`
   - VPC: select `Architecture`
   - Add subnets: select `app-subnet` (and other private subnets if available)
3. Click **Create**

---

## Step 17 — Create RDS Database (Easy Create)

1. RDS → Databases → Create database
2. Use **Easy create** (or Standard create for more control)
3. Choose:
   - Engine: **MySQL**
   - Template: **Free tier** (if eligible)
4. Configuration:
   - DB instance identifier: `database-1`
   - Master username: `admin`
   - Master password: `pass$123` (example — use a secure password)
5. Connectivity:
   - VPC: `Architecture`
   - Public accessibility: **No**
   - DB subnet group: `db-subnet-group`
   - VPC security group: create/select `rds-sg` that allows inbound 3306 from `app-sg`
6. (Optional) Set up EC2 connection: choose App server as the EC2 compute resource if prompted.
7. Create Database — wait until status shows **Available**.

---

## Step 18 — Configure `submit.php` with RDS endpoint

1. After DB is **available**, open database instance and copy **Endpoint** (example: `database-1.cynimo08yl9j.us-east-1.rds.amazonaws.com`).
2. Edit `submit.php` on App Server:

```bash
sudo nano /var/www/html/submit.php
```

Replace the connection variables:

```php
<?php
$servername = "database-1.cynimo08yl9j.us-east-1.rds.amazonaws.com";  // Database host(endpoint>
$username = "admin";         // Database username
$password = "pass$123";             // Database password
$dbname = "studentdb";      // Database name
// ... rest of script unchanged ...
?>
```

Save and exit.

Restart services if needed:

```bash
sudo service nginx restart
sudo service nginx reload
sudo service php8.3-fpm restart
sudo service php8.3-fpm reload
```

---

## Step 19 — Create Database & Table in RDS

From the App Server, use the MySQL client:

```bash
sudo mysql -u admin -p -h database-1.cynimo08yl9j.us-east-1.rds.amazonaws.com
# enter password when prompted
```

In the MySQL shell:

```sql
create database studentdb;
use studentdb;
create table students(id int primary key auto_increment, fullname varchar(50),email varchar(50), phone bigint, course varchar(20));
desc students;
```
![mysql table](https://github.com/user-attachments/assets/4e8aafd5-12a8-4c27-95b9-4058e088afbe)

The image is the Proof that the table we Created certainly exists.

---

## Step 20 — Test the Application End-to-End

1. Open browser to:
```
http://<WEB_PUBLIC_IP>/form.html
```
2. Fill and submit the form with dummy data.
3. On the App Server, in MySQL:
```sql
select * from students;
```
You should see the submitted entries Ex - 

![last result mysql](https://github.com/user-attachments/assets/ddb56950-228d-44a2-b8a5-be957048198d)

---

# Verification & Troubleshooting

## Verification Checklist

- [ ] VPC `10.0.0.0/16` created
- [ ] `web-subnet` and `app-subnet` created & associated correctly
- [ ] IGW attached to VPC and `public-rt` has `0.0.0.0/0` → IGW
- [ ] NAT Gateway created in public subnet; `private-rt` routes `0.0.0.0/0` → NAT
- [ ] EC2 Web instance has public IP and `web-sg` open to HTTP/SSH
- [ ] EC2 App instance is private and `app-sg` allows HTTP from `web-sg`
- [ ] RDS instance available and `rds-sg` allows 3306 from `app-sg`
- [ ] Form available at `http://<WEB_PUBLIC_IP>/form.html`
- [ ] Data saved to RDS `studentdb.students`

## Common Troubleshooting

**Web form not reachable**
- Confirm EC2 Web instance public IP and that `web-sg` allows HTTP 80.
- Check Nginx status: `sudo service nginx status`.
- Check file `form.html` is located in `/var/www/html/`.

**Proxy or PHP not working**
- Confirm `proxy_pass` uses correct App Server private IP.

**RDS connection fails**
- Verify `rds-sg` inbound rule allows MySQL 3306 from `app-sg`.
- Confirm endpoint and credentials in `submit.php`.

---

# Cleanup (Delete resources to avoid charges)

1. Terminate EC2 instances (Web & App).
2. Delete RDS instance (take snapshot if needed).
3. Delete NAT Gateway (release the Elastic IP).
4. Delete subnets, route tables, internet gateway.
5. Delete the VPC.

> Important: Delete NAT Gateway before deleting the Elastic IP to avoid stranded resources and charges.

---
# AWS VPC Components Comparison
| **Characteristic** | **VPC** | **Subnet** | **Route Table** | **Internet Gateway** | **NAT Gateway** | **EC2 Instance** | **RDS Database** |
|--------------------|---------|-------------|------------------|----------------------|-----------------|------------------|------------------|
| **Purpose** | Parent container for all components | Subdivision of VPC, lives in AZ | Defines traffic paths for subnets | Connects VPC to the Internet | Enables outbound internet access for private subnets | Compute layer for applications | Data layer for storing information |
| **Location** | Exists as a private network space | Lives in a specific Availability Zone | Associated with subnets | Attaches directly to the VPC | Sits inside a Public Subnet | Web Tier in Public Subnet, App Tier in Private Subnet | Deployed inside the Private Subnet |
| **Connectivity** | Connects to Internet Gateway | Connected to Route Table | Points to Internet Gateway or NAT Gateway | Connects VPC to the Internet | Uses Internet Gateway for outbound traffic | Web Tier to Internet Gateway, App Tier to NAT Gateway | Connects to App Tier via VPC internal routing |
| **Access** | Acts as a private network space | Public or Private | Public Route Table enables public access, Private Route Table enables indirect access | Enables public access for subnets | Allows internet access for private instances, remains unreachable from internet | Web Tier handles web traffic, App Tier talks to web tier and database | Only App Tier can communicate directly |


---

# Appendix: Useful commands

```bash
# Common package installs
sudo apt update
sudo apt install -y nginx php8.3 php8.3-fpm php8.3-mysql mariadb-client

# Start/Restart services
sudo service nginx start
sudo service nginx restart
sudo service nginx reload
sudo service php8.3-fpm restart
sudo service php8.3-fpm reload

# SSH & SCP examples (PowerShell/Windows)
scp -i .\path\to\key.pem .\path\to\key.pem ubuntu@<WEB_PUBLIC_IP>:.
ssh -i .\path\to\key.pem ubuntu@<WEB_PUBLIC_IP>

# Connect to RDS (from App server)
sudo mysql -u admin -p -h <rds-endpoint>
```
---

# Cleanup Checklist (reminder)

- Terminate EC2 instances (Web & App).
- Delete RDS instance (snapshot if needed).
- Delete NAT Gateway and release EIP.
- Delete Internet Gateway, Route Tables, Subnets.
- Delete VPC.

---

## Author
**Rushikesh Dase**  
Cloud Computing Enthusiast | AWS Learner  
📧 daserushikesh@gmail.com  
🔗 https://www.linkedin.com/in/rushi-dase  
**Category:** AWS | Cloud Computing | VPC | EC2 | RDS | Networking
