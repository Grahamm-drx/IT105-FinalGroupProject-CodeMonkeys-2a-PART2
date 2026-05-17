# Database Normalization Report

## Objective

Document the normalization process and verify that all tables conform to Third Normal Form (3NF) requirements, ensuring data integrity, consistency, and optimal query performance.

---

## Normalization Process & Analysis

### 1NF Compliance (Atomic Values)

All attributes contain only atomic (indivisible) values:

- **Name fields:** firstName, lastName (separated, not combined)
- **Email fields:** Single email per row
- **Date fields:** Stored as DATE type (not combined with time)
- **Grade field:** Single value (A+, A, B, etc.) not comma-separated lists
- **Status field:** Single ENUM value

✅ **All tables satisfy 1NF requirements**

---

### 2NF Compliance (No Partial Dependencies)

No partial dependencies exist where a non-key attribute depends on only part of a composite key:

| Table | Primary Key | Analysis |
|-------|-------------|----------|
| users | userID (single) | N/A - Single key |
| students | studentID (single) | N/A - Single key |
| instructors | instructorID (single) | N/A - Single key |
| courses | courseID (single) | N/A - Single key |
| enrollments | enrollment_id (single) | N/A - Single key; note: UNIQUE(studentID, courseID) prevents duplicates |
| audit_logs | logID (single) | N/A - Single key |
| grades_log | grade_log_id (single) | N/A - Single key |

**Key Note:** enrollments table uses a junction table design with single primary key + UNIQUE constraint, which is both normalized and efficient.

✅ **All tables satisfy 2NF requirements**

---

### 3NF Compliance (No Transitive Dependencies)

No non-key attributes depend on other non-key attributes:

#### users Table

```
PK: userID
Attributes: username, password, role, created_at
Analysis: All attributes depend directly on userID
No transitive dependency exists
```

✅ **3NF Compliant**

#### students Table

```
PK: studentID
Attributes: firstName, lastName, email, created_at
Analysis: All attributes describe the student
No transitive dependencies
Example: firstName does NOT depend on lastName
```

✅ **3NF Compliant**

#### instructors Table

```
PK: instructorID
Attributes: name, email, created_at
Analysis: All attributes depend directly on instructorID
Email does NOT transitively depend on name
```

✅ **3NF Compliant**

#### courses Table

```
PK: courseID
FK: instructorID → instructors(instructorID)
Attributes: courseName, description, credits, created_at
Analysis:
- courseName depends directly on courseID
- instructorID is a proper FK to instructors table (correctly normalized)
- credits depends on courseID, not on instructorID
```

✅ **3NF Compliant**

#### enrollments Table (Fact Table)

```
PK: enrollment_id
FK1: studentID → students(studentID)
FK2: courseID → courses(courseID)
Attributes: enrollmentDate, grade, status, created_at
Analysis:
- This is a junction (bridge) table for many-to-many relationship
- enrollment_id is single PK (unique enrollment record identifier)
- UNIQUE(studentID, courseID) ensures no duplicate enrollments
- UNIQUE constraint is properly indexed
- All attributes depend on enrollment_id
```

✅ **3NF Compliant - Properly Implemented Many-to-Many**

#### audit_logs Table

```
PK: logID
Attributes: action, tableName, actionTime, details
Analysis: All attributes describe a single audit event
All attributes depend directly on logID
```

✅ **3NF Compliant**

#### grades_log Table

```
PK: grade_log_id
FK: enrollment_id → enrollments(enrollment_id)
Attributes: old_grade, new_grade, changed_by, changed_at
Analysis: All attributes relate to a single grade change event
All depend directly on grade_log_id
```

✅ **3NF Compliant**

---

## Entity Relationship Diagram (Logical)

```
┌──────────────────┐
│      users       │
├──────────────────┤
│ userID (PK)      │
│ username (UNIQUE)│
│ password         │
│ role             │
│ created_at       │
└──────────────────┘

┌──────────────────┐
│    students      │
├──────────────────┤
│ studentID (PK)   │
│ firstName        │
│ lastName         │
│ email (UNIQUE)   │
│ created_at       │
└──────────────────┘
         │
         │ 1:M
         ├──────────────────┐
         │                  │
┌────────┴──────────────┐ ┌─┴──────────────────┐
│   enrollments (fact)  │ │  courses           │
├──────────────────────┤ ├────────────────────┤
│ enrollment_id (PK)   │ │ courseID (PK)      │
│ studentID (FK)       │─┤ courseName         │
│ courseID (FK)        │ │ instructorID (FK)──┐
│ enrollmentDate       │ │ description        │
│ grade                │ │ credits            │
│ status               │ │ created_at         │
│ created_at           │ └────────────────────┘
│ UNIQUE(studentID,    │         │
│  courseID)           │         │ 1:M
└──────────────────────┘         │
         │                       │
         │ 1:M            ┌──────┴──────┐
         │                │ instructors │
┌────────┴──────────────┐ ├─────────────┤
│   grades_log         │ │ instructorID │
├──────────────────────┤ │ name         │
│ grade_log_id (PK)    │ │ email (UNIQUE)
│ enrollment_id (FK)   │ │ created_at   │
│ old_grade            │ └──────────────┘
│ new_grade            │
│ changed_by           │
│ changed_at           │
└──────────────────────┘

┌──────────────────┐
│  audit_logs      │
├──────────────────┤
│ logID (PK)       │
│ action           │
│ tableName        │
│ actionTime       │
│ details          │
└──────────────────┘
```

---

## Relationships Analysis

### Foreign Key Relationships

| Relationship | Type | Integrity Rule |
|-------------|------|-----------------|
| courses.instructorID → instructors.instructorID | M:1 | RESTRICT (prevent course deletion if instructor assignment) |
| enrollments.studentID → students.studentID | M:1 | CASCADE (delete enrollments when student is removed) |
| enrollments.courseID → courses.courseID | M:1 | RESTRICT (prevent course deletion if enrollments exist) |
| enrollments.studentID + enrollments.courseID | UNIQUE | Prevents duplicate enrollments |
| grades_log.enrollment_id → enrollments.enrollment_id | M:1 | CASCADE (audit trail of all grade changes) |

### Many-to-Many Relationship

**Students ←→ Courses via Enrollments**

```
1 Student can be enrolled in MANY Courses
1 Course can have MANY Students enrolled
Resolved by: enrollments junction table
Constraints: UNIQUE(studentID, courseID)
Additional attributes: enrollmentDate, grade, status
```

✅ **Properly normalized M:M relationship**

---

## Constraints & Data Integrity

### Primary Key Constraints

- ✅ Every table has a surrogate primary key (INT AUTO_INCREMENT)
- ✅ Primary keys are properly indexed

### Unique Constraints

- ✅ users.username (prevents duplicate usernames)
- ✅ students.email (prevents duplicate student emails)
- ✅ instructors.email (prevents duplicate instructor emails)
- ✅ enrollments(studentID, courseID) - composite unique constraint

### Not Null Constraints

- ✅ All identity/name fields are NOT NULL
- ✅ Foreign keys properly constrained
- ✅ Status fields have DEFAULT values

### Foreign Key Constraints

- ✅ Referential integrity enforced at database level
- ✅ Proper ON DELETE/UPDATE actions configured
- ✅ Cascade deletes for audit trail

---

## Normalization Benefits Achieved

1. **Data Integrity:** Constraints prevent invalid data entry
2. **Consistency:** Single sources of truth eliminate redundancy
3. **Referential Integrity:** Foreign keys maintain relationship validity
4. **Query Efficiency:** Proper joins on indexed columns
5. **Maintainability:** Clear, logical table structure
6. **Scalability:** Design supports 100,000+ rows efficiently

---

## Conclusion

✅ **All tables conform to Third Normal Form (3NF)**

The database design eliminates:

- ✅ Repeating groups (1NF)
- ✅ Partial dependencies (2NF)
- ✅ Transitive dependencies (3NF)

The normalization is appropriate for the application requirements and balances normalization with practical query performance needs. The junction table design for many-to-many relationships is optimal for this use case.
