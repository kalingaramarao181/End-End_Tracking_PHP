-- Change request to load data into Genius Project Management Tool (GPMT):

INSERT INTO dbs13500099.admin (created_on, firstname, lastname, password, photo, username)
VALUES
('2024-01-01 10:00:00', 'John', 'Doe', 'hashedpassword1', 'photo1.jpg', 'johndoe'),
('2024-01-02 10:10:00', 'Alice', 'Smith', 'hashedpassword2', 'photo2.jpg', 'alicesmith'),
('2024-01-03 10:20:00', 'Bob', 'Johnson', 'hashedpassword3', 'photo3.jpg', 'bobjohnson'),
('2024-01-04 10:30:00', 'Charlie', 'Brown', 'hashedpassword4', 'photo4.jpg', 'charliebrown');

INSERT INTO dbs13500099.attendancereport (id, employee_id, date, time_in, status, time_out, num_hr)
VALUES
(1, 101, '2025-02-01', '09:00:00', 1, '17:00:00', 8.0),
(2, 102, '2025-02-01', '09:15:00', 1, '17:15:00', 8.0),
(3, 103, '2025-02-01', '08:45:00', 1, '16:45:00', 8.0),
(100, 200, '2025-02-10', '09:10:00', 1, '17:10:00', 8.0);
