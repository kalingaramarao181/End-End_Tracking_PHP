CREATE TABLE MedicalRecords (
    RecordID INT PRIMARY KEY AUTO_INCREMENT,
    PatientID INT NOT NULL,
    DoctorID INT NOT NULL,
    Diagnosis TEXT,
    Treatment TEXT,
    Prescription TEXT,
    RecordDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_patient FOREIGN KEY (PatientID) REFERENCES Patients(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_doctor FOREIGN KEY (DoctorID) REFERENCES Doctors(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;


CREATE TABLE Billing (
    BillID INT PRIMARY KEY AUTO_INCREMENT,
    PatientID INT,
    AppointmentID INT,
    TotalAmount DECIMAL(10,2),
    PaymentStatus ENUM('Pending', 'Paid', 'Cancelled'),
    PaymentDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (PatientID) REFERENCES Patients(id) ON DELETE CASCADE,
    FOREIGN KEY (AppointmentID) REFERENCES Appointments(id) ON DELETE CASCADE
);


CREATE TABLE Staff (
    StaffID INT PRIMARY KEY AUTO_INCREMENT,
    FirstName VARCHAR(50),
    LastName VARCHAR(50),
    Role VARCHAR(100),
    ContactNumber VARCHAR(15),
    Email VARCHAR(100) UNIQUE,
    HireDate DATE
);

CREATE TABLE Rooms (
    RoomID INT PRIMARY KEY AUTO_INCREMENT,
    RoomType ENUM('General', 'ICU', 'Private', 'Surgery'),
    Status ENUM('Available', 'Occupied', 'Maintenance'),
    Capacity INT,
    AssignedPatientID INT NULL,
    FOREIGN KEY (AssignedPatientID) REFERENCES Patients(PatientID) ON DELETE SET NULL
);


CREATE TABLE Inventory (
    ItemID INT PRIMARY KEY AUTO_INCREMENT,
    ItemName VARCHAR(100),
    Category VARCHAR(50),
    Quantity INT,
    ExpiryDate DATE,
    Supplier VARCHAR(100)
);
