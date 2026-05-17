#!/usr/bin/env python3
"""
Dataset Generator for Student Management System
Generates 100,000+ rows for comprehensive testing and benchmarking
"""

import random
import datetime

# Configuration
TOTAL_ROWS = 150000
OUTPUT_FILE = "dataset_large.sql"

# Sample data
first_names = ["John", "Jane", "Michael", "Emily", "David", "Sarah", "Robert", "Lisa", "James", "Mary",
               "Charles", "Patricia", "Christopher", "Linda", "Daniel", "Barbara", "Matthew", "Jennifer",
               "Anthony", "Maria", "Mark", "Susan", "Donald", "Jessica", "Steven", "Sarah", "Andrew", "Karen",
               "Kenneth", "Nancy", "Kevin", "Carol", "Brian", "Sandra", "George", "Catherine", "Edward", "Christine",
               "Ronald", "Deborah", "Timothy", "Rachel", "Jason", "Cynthia", "Jeffrey", "Kathleen", "Ryan", "Shirley"]

last_names = ["Smith", "Johnson", "Williams", "Brown", "Garcia", "Miller", "Davis", "Rodriguez", "Martinez", "Lee",
              "Taylor", "Anderson", "Thomas", "Spencer", "Hill", "Clark", "Lewis", "Walker", "Hall", "Allen",
              "Young", "King", "Wright", "Scott", "Green", "Adams", "Nelson", "Carter", "Mitchell", "Roberts",
              "Phillips", "Evans", "Turner", "Diaz", "Parker", "Edwards", "Collins", "Reeves", "Stewart", "Sanchez",
              "Morris", "Pena", "Ramirez", "Barnes", "Grant", "Burgess", "Pitts", "Savage", "Bridges", "Saunders"]

grades = ["A+", "A", "A-", "B+", "B", "B-", "C+", "C", "C-", "D+", "D", "F", None]
statuses = ["active", "completed", "dropped"]

courses_data = [
    ("Database Systems", 1, "Introduction to relational databases and SQL", 3),
    ("Web Development", 2, "Frontend and backend web development with PHP", 4),
    ("Data Structures", 3, "Advanced data structures and algorithms", 3),
    ("Object-Oriented Programming", 1, "OOP concepts in Java and Python", 3),
    ("Information Management", 2, "Database design and information systems", 3),
    ("Cloud Computing", 3, "AWS and cloud infrastructure", 4),
    ("Software Engineering", 1, "Software development lifecycle and methodologies", 3),
    ("Cybersecurity Basics", 2, "Network security and threat prevention", 3),
    ("Advanced SQL", 3, "Complex queries and database optimization", 4),
    ("IT Systems Design", 1, "Enterprise system architecture", 3),
]

instructors_data = [
    ("Dr. Smith", "smith@university.edu"),
    ("Prof. Johnson", "johnson@university.edu"),
    ("Dr. Williams", "williams@university.edu"),
    ("Dr. Brown", "brown@university.edu"),
    ("Prof. Garcia", "garcia@university.edu"),
    ("Dr. Miller", "miller@university.edu"),
    ("Prof. Davis", "davis@university.edu"),
    ("Dr. Rodriguez", "rodriguez@university.edu"),
    ("Prof. Martinez", "martinez@university.edu"),
    ("Dr. Lee", "lee@university.edu"),
]

print(f"Generating dataset with {TOTAL_ROWS} total rows...")

with open(OUTPUT_FILE, "w", encoding="utf-8") as f:
    f.write("USE student_management_systemDB;\n\n")
    
    # Users - 200 users
    f.write("-- Users\n")
    f.write("INSERT INTO users (username, password, role) VALUES\n")
    user_inserts = []
    for i in range(1, 201):
        role = random.choice(["admin", "staff"])
        username = f"user{i}"
        password = f"pass{i}"
        user_inserts.append(f"('{username}', '{password}', '{role}')")
    
    f.write(",\n".join(user_inserts) + ";\n\n")
    
    # Instructors - 100 instructors
    f.write("-- Instructors\n")
    f.write("INSERT INTO instructors (name, email) VALUES\n")
    instructor_inserts = []
    
    # First add the predefined instructors
    for name, email in instructors_data:
        instructor_inserts.append(f"('{name}', '{email}')")
    
    # Then add more random instructors
    for i in range(1, 91):
        first = random.choice(first_names)
        last = random.choice(last_names)
        name = f"Dr. {first} {last}"
        email = f"{first.lower()}_{last.lower()}_{i}@university.edu"
        instructor_inserts.append(f"('{name}', '{email}')")
    
    f.write(",\n".join(instructor_inserts) + ";\n\n")
    
    # Courses - 500 courses
    f.write("-- Courses\n")
    f.write("INSERT INTO courses (courseName, instructorID, description, credits) VALUES\n")
    course_inserts = []
    
    # Add base courses
    for idx, (name, instr_id, desc, credits) in enumerate(courses_data):
        course_inserts.append(f"('{name}', {instr_id}, '{desc}', {credits})")
    
    # Add more courses
    course_templates = [
        "Advanced {subject}",
        "Introduction to {subject}",
        "Practical {subject}",
        "Theory of {subject}",
        "{subject} Systems",
        "Applied {subject}",
        "Professional {subject}"
    ]
    
    subjects = ["Programming", "Database", "Networking", "Security", "Design", "Management", "Analytics", "Testing"]
    
    for i in range(len(course_inserts), 500):
        template = random.choice(course_templates)
        subject = random.choice(subjects)
        course_name = template.format(subject=subject)
        instr_id = random.randint(1, 100)
        credits = random.choice([3, 4])
        course_inserts.append(f"('{course_name}', {instr_id}, 'Course description', {credits}')")
    
    f.write(",\n".join(course_inserts[:500]) + ";\n\n")
    
    # Students - 50,000 students
    f.write("-- Students (50,000 rows)\n")
    f.write("-- Batch 1: 25,000 students\n")
    student_inserts = []
    for i in range(1, 25001):
        first = random.choice(first_names)
        last = random.choice(last_names)
        email = f"{first.lower()}_{last.lower()}_{i}@university.edu"
        student_inserts.append(f"('{first}', '{last}', '{email}')")
    
    f.write("INSERT INTO students (firstName, lastName, email) VALUES\n")
    f.write(",\n".join(student_inserts) + ";\n\n")
    
    # Batch 2: next 25,000 students
    f.write("-- Batch 2: Additional 25,000 students\n")
    student_inserts = []
    for i in range(25001, 50001):
        first = random.choice(first_names)
        last = random.choice(last_names)
        email = f"{first.lower()}_{last.lower()}_{i}@university.edu"
        student_inserts.append(f"('{first}', '{last}', '{email}')")
    
    f.write("INSERT INTO students (firstName, lastName, email) VALUES\n")
    f.write(",\n".join(student_inserts) + ";\n\n")
    
    # Enrollments - 60,000+ enrollments
    f.write("-- Enrollments (60,000+ rows)\n")
    f.write("-- Batch 1: 30,000 enrollments\n")
    enrollment_inserts = []
    base_date = datetime.date(2024, 1, 1)
    
    for i in range(1, 30001):
        student_id = random.randint(1, 50000)
        course_id = random.randint(1, 500)
        days_offset = random.randint(0, 365)
        enroll_date = base_date + datetime.timedelta(days=days_offset)
        grade = random.choice(grades)
        status = random.choice(statuses)
        
        grade_str = f"'{grade}'" if grade else "NULL"
        enrollment_inserts.append(f"({student_id}, {course_id}, '{enroll_date}', {grade_str}, '{status}')")
    
    f.write("INSERT INTO enrollments (studentID, courseID, enrollmentDate, grade, status) VALUES\n")
    f.write(",\n".join(enrollment_inserts) + ";\n\n")
    
    # Batch 2: 30,000 more enrollments
    f.write("-- Batch 2: 30,000 more enrollments\n")
    enrollment_inserts = []
    for i in range(30001, 60001):
        student_id = random.randint(1, 50000)
        course_id = random.randint(1, 500)
        days_offset = random.randint(0, 365)
        enroll_date = base_date + datetime.timedelta(days=days_offset)
        grade = random.choice(grades)
        status = random.choice(statuses)
        
        grade_str = f"'{grade}'" if grade else "NULL"
        enrollment_inserts.append(f"({student_id}, {course_id}, '{enroll_date}', {grade_str}, '{status}')")
    
    f.write("INSERT INTO enrollments (studentID, courseID, enrollmentDate, grade, status) VALUES\n")
    f.write(",\n".join(enrollment_inserts) + ";\n\n")
    
    f.write("-- Dataset generation complete!\n")

print(f"Dataset generated successfully! File: {OUTPUT_FILE}")
print(f"Instructions:")
print(f"1. Login to phpMyAdmin: http://localhost/phpmyadmin")
print(f"2. Select the database: student_management_systemDB")
print(f"3. Click 'Import'")
print(f"4. Upload the {OUTPUT_FILE} file")
print(f"5. Click 'Go' to import")


    # Instructors
    for i in range(1, INSTRUCTORS + 1):
        f.write(
            f"INSERT INTO instructors (name, email) VALUES "
            f"('{fake.name()}', 'instructor{i}@example.com');\n"
        )

    # Courses (each assigned to a random instructor)
    for i in range(1, COURSES + 1):
        instructor_id = random.randint(1, INSTRUCTORS)
        f.write(
            f"INSERT INTO courses (courseName, instructorID) VALUES "
            f"('Course {i}', {instructor_id});\n"
        )

    # Enrollments (many-to-many)
    for i in range(1, ENROLLMENTS + 1):
        student_id = random.randint(1, STUDENTS)
        course_id = random.randint(1, COURSES)
        grade = random.choice(["A", "B", "C", "D", "F"])
        date = fake.date()
        f.write(
            f"INSERT INTO enrollments (studentID, courseID, enrollmentDate, grade) VALUES "
            f"({student_id}, {course_id}, '{date}', '{grade}');\n"
        )

    # Audit logs (5000 dummy entries)
    for i in range(1, AUDIT_LOGS + 1):
        f.write(
            f"INSERT INTO audit_logs (action, tableName) VALUES "
            f"('INSERT', 'students');\n"
        )

print("✅ dataset.sql generated successfully!")