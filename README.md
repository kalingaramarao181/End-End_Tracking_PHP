Authentication Module
This is the first module to test in Postman.
________________________________________
1. Register First Admin User
Use this only once to create your first admin account.
Method
POST
URL
http://localhost/api/user/register
Headers
Content-Type: application/json
Body
{
  "employee_id": 1,
  "nick_name": "Admin",
  "email": "admin@bedatatech.com",
  "password": "admin@123",
  "position_id": 1,
  "status": "Active"
}
Success Response
{
  "success": true,
  "message": "User created successfully.",
  "user_id": 1
}
________________________________________
2. Login
Method
POST
URL
http://localhost/api/auth/login
Headers
Content-Type: application/json
Body
{
  "email": "admin@bedatatech.com",
  "password": "admin@123"
}
Success Response
{
  "success": true,
  "message": "Login successful.",
  "token": "YOUR_JWT_TOKEN",
  "user": {
    "id": 1,
    "nick_name": "Admin",
    "email": "admin@bedatatech.com"
  }
}
________________________________________
3. Use JWT Token in Protected Requests
Copy the token from login response.
Use this header in all protected APIs:
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
________________________________________
4. Get Logged-In User Resources (Sidebar Menus)
Method
GET
URL
http://localhost/api/resource/my
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Success Response
{
  "success": true,
  "user_id": 1,
  "total_resources": 6,
  "data": [
    {
      "resource_name": "dashboard",
      "display_name": "Dashboard",
      "route": "/dashboard"
    },
    {
      "resource_name": "users",
      "display_name": "Users",
      "route": "/users"
    }
  ]
}
________________________________________
Recommended Testing Order
1.	Create required positions, resources, and permissions data. 
2.	Register first admin user. 
3.	Login. 
4.	Copy JWT token. 
5.	Use the token for all other APIs. 
________________________________________
Common Errors
Token Missing
{
  "error": "Token missing"
}
Add:
Authorization: Bearer YOUR_JWT_TOKEN
Invalid Token
{
  "error": "Invalid token"
}
Login again and copy a fresh token.


___________________________________________________________________________________________________________________

User Module
All User APIs require the JWT token obtained from the Authentication Module.
________________________________________
Common Headers for Protected APIs
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
________________________________________
1. Create User
Method
POST
URL
http://localhost/api/user/register
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
Body
{
  "employee_id": 2,
  "nick_name": "John",
  "email": "john@bedatatech.com",
  "password": "john@123",
  "position_id": 3,
  "status": "Active"
}
Success Response
{
  "success": true,
  "message": "User created successfully.",
  "user_id": 2
}
________________________________________
2. List Users
Method
GET
URL
http://localhost/api/user/list?page=1&limit=10&search=John
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Success Response
{
  "success": true,
  "page": 1,
  "limit": 10,
  "search": "John",
  "total_records": 1,
  "total_pages": 1,
  "data": [
    {
      "id": 2,
      "employee_id": 2,
      "nick_name": "John",
      "email": "john@bedatatech.com",
      "position_id": 3,
      "position_name": "Bench Sales",
      "status": "Active"
    }
  ]
}
________________________________________
3. Get User by ID
Method
GET
URL
http://localhost/api/user/2
Headers
Authorization: Bearer YOUR_JWT_TOKEN
________________________________________
4. Update User
Method
PUT
URL
http://localhost/api/user/update/2
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
Body
{
  "employee_id": 2,
  "nick_name": "John",
  "email": "john@bedatatech.com",
  "password": "john@456",
  "position_id": 3,
  "status": "Active"
}
Success Response
{
  "success": true,
  "message": "User updated successfully."
}
________________________________________
5. Delete User
Method
DELETE
URL
http://localhost/api/user/delete/2
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Success Response
{
  "success": true,
  "message": "User deleted successfully."
}
________________________________________
6. Update Another User's Password (Admin)
Method
PUT
URL
http://localhost/api/user/update/11
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
Body
{
  "employee_id": 11,
  "nick_name": "Sharon",
  "email": "sharon@bedatatech.com",
  "password": "sharon@123",
  "position_id": 3,
  "status": "Active"
}

___________________________________________________________________________________________________________________


Employee Module
All Employee APIs require the JWT token obtained from the Authentication Module.
________________________________________
Common Headers for Protected APIs
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
________________________________________
1. Create Employee
Method
POST
URL
http://localhost/api/employee/create
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
Body
{
  "name": "John Doe",
  "email": "john.doe@bedatatech.com",
  "phone": "9876543210",
  "status": "Active"
}
Success Response
{
  "success": true,
  "message": "Employee created successfully.",
  "employee_id": 121
}
________________________________________
2. List Employees
Method
GET
URL
http://localhost/api/employee/list?page=1&limit=10&search=John
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Success Response
{
  "success": true,
  "page": 1,
  "limit": 10,
  "search": "John",
  "total_records": 1,
  "total_pages": 1,
  "data": [
    {
      "id": 121,
      "name": "John Doe",
      "email": "john.doe@bedatatech.com",
      "phone": "9876543210",
      "status": "Active"
    }
  ]
}
________________________________________
3. Get Employee by ID
Method
GET
URL
http://localhost/api/employee/121
Headers
Authorization: Bearer YOUR_JWT_TOKEN
________________________________________
4. Update Employee
Method
PUT
URL
http://localhost/api/employee/update/121
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
Body
{
  "name": "John Doe",
  "email": "john.doe@bedatatech.com",
  "phone": "9999999999",
  "status": "Active"
}
Success Response
{
  "success": true,
  "message": "Employee updated successfully."
}
________________________________________
5. Delete Employee
Method
DELETE
URL
http://localhost/api/employee/delete/121
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Success Response
{
  "success": true,
  "message": "Employee deleted successfully."
}


___________________________________________________________________________________________________________________


Position Module
All Position APIs require the JWT token obtained from the Authentication Module.
________________________________________
Common Headers for Protected APIs
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
________________________________________
1. Create Position
Method
POST
URL
http://localhost/api/position/create
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
Body
Team Lead
{
  "position_name": "Team Lead",
  "description": "Manages team members and oversees operations.",
  "status": "Active"
}
________________________________________
2. List Positions
Method
GET
URL
http://localhost/api/position/list?page=1&limit=10&search=Bench
Headers
Authorization: Bearer YOUR_JWT_TOKEN
________________________________________
3. Get Position by ID
Method
GET
URL
http://localhost/api/position/1
Headers
Authorization: Bearer YOUR_JWT_TOKEN
________________________________________
4. Update Position
Method
PUT
URL
http://localhost/api/position/update/1
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
Body
{
  "position_name": "Team Lead",
  "description": "Leads and manages the sales team.",
  "status": "Active"
}
________________________________________
5. Delete Position
Method
DELETE
URL
http://localhost/api/position/delete/3
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Success Response
{
  "success": true,
  "message": "Position deleted successfully."
}


___________________________________________________________________________________________________________________

Resource Module
All Resource APIs require the JWT token obtained from the Authentication Module.
________________________________________
Common Headers for Protected APIs
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
________________________________________
1. Create Resource
Method
POST
URL
http://localhost/api/resource/create
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
Body
Positions Resource
{
  "resource_name": "positions",
  "display_name": "Positions",
  "icon": "Briefcase",
  "route": "/positions",
  "parent_id": null,
  "sort_order": 1,
  "status": "Active"
}  
________________________________________
2. List Resources
Method
GET
URL
http://localhost/api/resource/list?page=1&limit=10&search=user
Headers
Authorization: Bearer YOUR_JWT_TOKEN
________________________________________
3. Get Resource by ID
Method
GET
URL
http://localhost/api/resource/1
Headers
Authorization: Bearer YOUR_JWT_TOKEN
________________________________________
4. Update Resource
Method
PUT
URL
http://localhost/api/resource/update/1
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
Body
{
  "resource_name": "positions",
  "display_name": "Position Management",
  "icon": "Briefcase",
  "route": "/positions",
  "parent_id": null,
  "sort_order": 1,
  "status": "Active"
}
________________________________________
5. Delete Resource
Method
DELETE
URL
http://localhost/api/resource/delete/1
Headers
Authorization: Bearer YOUR_JWT_TOKEN
________________________________________
6. Get Logged-In User Resources (Sidebar Menu)
Returns only the resources where the logged-in user has can_view = 1.
Method
GET
URL
http://localhost/api/resource/my
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Example Response
{
  "success": true,
  "user_id": 1,
  "total_resources": 5,
  "data": [
    {
      "resource_name": "positions",
      "display_name": "Positions",
      "route": "/positions"
    },
    {
      "resource_name": "users",
      "display_name": "Users",
      "route": "/users"
    }
  ]
}


___________________________________________________________________________________________________________________

Permission Module
All Permission APIs require the JWT token obtained from the Authentication Module.
________________________________________
Common Headers for Protected APIs
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
________________________________________
1. Get Permissions by Position
Returns all resources and their permissions for a given position.
Method
GET
URL
http://localhost/api/permission/position/1
Replace 1 with the position_id.
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Example Response
{
  "success": true,
  "position_id": 1,
  "data": [
    {
      "resource_id": 1,
      "resource_name": "positions",
      "display_name": "Positions",
      "can_view": 1,
      "can_create": 1,
      "can_edit": 1,
      "can_delete": 1,
      "can_export": 1,
      "can_approve": 1
    }
  ]
}
________________________________________
2. Save / Update Permissions by Position
Creates or updates permissions for all resources for a given position.
Method
PUT
URL
http://localhost/api/permission/position/1
Replace 1 with the position_id.
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
Body
[
  {
    "resource_id": 1,
    "can_view": 1,
    "can_create": 1,
    "can_edit": 1,
    "can_delete": 1,
    "can_export": 1,
    "can_approve": 1
  },
  {
    "resource_id": 2,
    "can_view": 1,
    "can_create": 1,
    "can_edit": 1,
    "can_delete": 1,
    "can_export": 0,
    "can_approve": 0
  },
  {
    "resource_id": 3,
    "can_view": 1,
    "can_create": 1,
    "can_edit": 1,
    "can_delete": 1,
    "can_export": 0,
    "can_approve": 0
  }
]
Success Response
{
  "success": true,
  "message": "Permissions saved successfully."
}
________________________________________
3. Delete All Permissions for a Position
Removes all permission rows for the specified position.
Method
DELETE
URL
http://localhost/api/permission/position/1
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Success Response
{
  "success": true,
  "message": "Permissions deleted successfully."
}
________________________________________
4. Get Effective Permissions by User
Returns final permissions for a specific user (position permissions + user overrides).
Method
GET
URL
http://localhost/api/permission/user/11
Replace 11 with the user_id.
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Example Response
{
  "success": true,
  "user_id": 11,
  "data": [
    {
      "resource_name": "users",
      "can_view": 1,
      "can_create": 1,
      "can_edit": 1,
      "can_delete": 0
    }
  ]
}
________________________________________
Recommended Setup Order
1.	Create Positions 
2.	Create Resources 
3.	Create Users 
4.	Assign Permissions to Positions 
5.	Login as a User 
6.	Call: 
o	GET /api/resource/my 
o	GET /api/permission/user/{user_id} 
________________________________________
Example: Give Full Access to Admin Position
If:
•	Admin position ID = 1 
Use:
PUT http://localhost/api/permission/position/1
With all resources and all permission flags set to 1.

Application Module – Postman Testing Requests
All Application APIs require a JWT token from login.
________________________________________
Common Headers
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
________________________________________
1. Create Application
Method
POST
URL
http://localhost/api/application/create
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
Body (Recommended for New Records)
{
  "date_created": "2026-05-21",
  "candidate_id": 15,
  "vendor": "Tech Solutions Inc",
  "poc": "Michael Johnson",
  "feedback": "Submitted to client",
  "client": "Google",
  "emp_loc": "Remote",
  "rate": "65",
  "role": "Java Developer",
  "candidate_loc": "Texas",
  "remarks": "Strong communication skills",
  "resume_path": "uploads/resumes/john-smith.pdf",
  "r2r_path": "uploads/r2r/john-smith-r2r.pdf",
  "driving_path": "uploads/driving/john-smith-license.pdf",
  "visa_path": "uploads/visa/john-smith-visa.pdf",
  "msc_path": "uploads/msa/john-smith-msa.pdf"
}
employee_id is automatically taken from the logged-in user JWT.
Success Response
{
  "success": true,
  "message": "Application created successfully.",
  "application_id": 1
}
________________________________________
2. Create Application (Legacy Support)
If candidate master is not available, you can still send candidate_name.
Body
{
  "date_created": "2026-05-21",
  "candidate_name": "John Smith",
  "vendor": "Tech Solutions Inc",
  "poc": "Michael Johnson",
  "feedback": "Submitted",
  "client": "Google",
  "emp_loc": "Remote",
  "rate": "65",
  "role": "Java Developer",
  "candidate_loc": "Texas",
  "remarks": "Legacy candidate record"
}
________________________________________
3. List Applications
Method
GET
URL
http://localhost/api/application/list?page=1&limit=10
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Behavior
•	Admin / Super Admin → sees all applications. 
•	Bench Sales / Recruiters → sees only their own applications. 
________________________________________
4. Search Applications
URL
http://localhost/api/application/list?page=1&limit=10&search=Google
Search works on:
•	Candidate Name 
•	Client 
•	Vendor 
•	Role 
•	Feedback 
________________________________________
5. Admin Filter by Position
Bench Sales Applications
http://localhost/api/application/list?page=1&limit=10&position_id=2
Recruiter Applications
http://localhost/api/application/list?page=1&limit=10&position_id=3
Search + Position Filter
http://localhost/api/application/list?page=1&limit=10&position_id=2&search=Java
Only Admin and Super Admin can use position_id to filter other users' data.
________________________________________
6. Get Application by ID
Method
GET
URL
http://localhost/api/application/1
Headers
Authorization: Bearer YOUR_JWT_TOKEN
________________________________________
7. Update Application
Method
PUT
URL
http://localhost/api/application/update/1
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
Body
{
  "date_created": "2026-05-21",
  "candidate_id": 15,
  "vendor": "Tech Solutions Inc",
  "poc": "Michael Johnson",
  "feedback": "Interview scheduled",
  "client": "Google",
  "emp_loc": "Remote",
  "rate": "70",
  "role": "Senior Java Developer",
  "candidate_loc": "Texas",
  "remarks": "Interview on Monday",
  "resume_path": "uploads/resumes/john-smith.pdf",
  "r2r_path": "uploads/r2r/john-smith-r2r.pdf",
  "driving_path": "uploads/driving/john-smith-license.pdf",
  "visa_path": "uploads/visa/john-smith-visa.pdf",
  "msc_path": "uploads/msa/john-smith-msa.pdf"
}
________________________________________
8. Delete Application
Method
DELETE
URL
http://localhost/api/application/delete/1
Headers
Authorization: Bearer YOUR_JWT_TOKEN
Success Response
{
  "success": true,
  "message": "Application deleted successfully."
}
________________________________________
9. Example Testing Flow
Login
POST http://localhost/api/auth/login
Create Application
POST http://localhost/api/application/create
List Applications
GET http://localhost/api/application/list
Admin Filter Bench Sales
GET http://localhost/api/application/list?position_id=2
Admin Filter Recruiters
GET http://localhost/api/application/list?position_id=3
Get Single Application
GET http://localhost/api/application/1
Update Application
PUT http://localhost/api/application/update/1
Delete Application
DELETE http://localhost/api/application/delete/1



___________________________________________________________________________________________________________________

Recommended End-to-End Testing Order
Follow this exact sequence when setting up your E2E Tracking backend from scratch.
________________________________________
1. Create Initial Data in Database
Before registering the first admin, make sure the following exist:
•	At least one employee (e.g., employee_id = 1) 
•	At least one position (e.g., position_id = 1, "Admin") 
________________________________________
2. Register First Admin User
POST http://localhost/api/user/register
________________________________________
3. Login
POST http://localhost/api/auth/login
Save the returned JWT token.
________________________________________
4. Create Resources
POST http://localhost/api/resource/create
Create these resources:
•	positions 
•	resources 
•	permissions 
•	users 
•	employees 
________________________________________
5. Assign Permissions to Admin Position
PUT http://localhost/api/permission/position/1
Grant all permissions (can_view, can_create, can_edit, can_delete) for all resources.
________________________________________
6. Verify Sidebar Resources for Admin
GET http://localhost/api/resource/my
________________________________________
7. Create Additional Positions
POST http://localhost/api/position/create
Examples:
•	Team Lead 
•	Bench Sales 
•	Web Developer 
•	Recruiter 
•	HR 
________________________________________
8. Create Employees
POST http://localhost/api/employee/create
________________________________________
9. Create Users
POST http://localhost/api/user/register
________________________________________
10. Assign Permissions to Other Positions
PUT http://localhost/api/permission/position/{position_id}
Examples:
•	Bench Sales: Applications only 
•	Recruiter: Candidates and Interviews 
•	HR: Employees and Users 
________________________________________
11. Login as Other Users
POST http://localhost/api/auth/login
________________________________________
12. Verify User Resources
GET http://localhost/api/resource/my
________________________________________
13. Verify Effective Permissions
GET http://localhost/api/permission/user/{user_id}
________________________________________
Modules Covered
1.	Authentication 
2.	Users 
3.	Employees 
4.	Positions 
5.	Resources 
6.	Permissions 
________________________________________
Full Setup Summary
1. Create Position (Admin)
2. Create Employee
3. Register Admin User
4. Login
5. Create Resources
6. Assign Admin Permissions
7. Verify /resource/my
8. Create Other Positions
9. Create Employees
10. Create Users
11. Assign Position Permissions
12. Login as Other Users
13. Verify Resources and Permissions

