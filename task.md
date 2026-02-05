# OSBB Management Platform

A multi-organization web application for managing ОСББ / HOA activities, allowing residents and administrators to collaborate via requests, surveys, and ownership-based voting.

The platform supports **multiple organizations**, **shared apartment ownership**, and **weighted voting based on owned square meters**.

---

## 1. Core Concepts

### 1.1 Organization (OSBB)
An **Organization** represents a single ОСББ / HOA.

Each Organization contains:
- One or more **building addresses**
- A **property registry** (apartments and ownership)
- **Users** with assigned roles
- **Requests** (tickets)
- **Surveys** (polls / voting)

All data is strictly isolated per organization.

---

### 1.2 User
A **User** is a person with a single account who may:
- Belong to multiple organizations
- Own multiple apartments
- Participate in surveys
- Submit requests

A user can have **different roles** in different organizations.

---

### 1.3 Roles

#### Organization Admin
Admin has **organization-level scope**.

Capabilities:
- Manage organization settings
- Manage addresses and buildings
- Import and maintain the property registry
- Approve or reject user access requests
- Create, publish, and close surveys
- View and manage all requests
- View detailed survey statistics

Admins may include:
- Board members
- Chairman
- Secretary
- Authorized managers

---

#### Regular User (Resident / Owner)

Capabilities:
- Join organizations
- Submit requests
- Comment on own requests
- Participate in surveys
- View public requests
- View survey results

---

## 2. Account & Access Flow

### 2.1 User Registration
A user creates an account using:
- Phone number
- Email
- Password

(Verification via SMS/email can be added later.)

---

### 2.2 Joining an Organization

1. User selects:
    - Organization
    - Address
    - Apartment
2. User submits a **join request**
3. Organization Admin:
    - Approves or rejects the request
4. After approval, the user gains access to organization data related to their assigned apartments

---

## 3. Property Registry & Ownership

### 3.1 Property Registry
The **property registry** is maintained by the Organization Admin and represents the source of truth.

Each registry entry includes:
- Building address
- Apartment number
- Owned square meters

Admins can:
- Add entries manually
- Import registry data via CSV / Excel

---

### 3.2 Ownership Model

- An apartment belongs to **one organization**
- An apartment may be owned by **multiple users**
- A user may own:
    - Multiple apartments
    - Across multiple organizations

Example:
User owns:
Apt 100 (50 m²) in OSBB #1
Apt 200 (70 m²) in OSBB #1
Apt 2 (45 m²) in OSBB #11


Ownership directly affects **survey voting weight**.

---

## 4. Requests (Tickets)

### 4.1 Purpose
Requests represent issues, proposals, or service needs.

Examples:
- Maintenance issues
- Complaints
- Improvement proposals
- Administrative requests

---

### 4.2 Request Structure
Each request contains:
- Title
- Description
- Attachments (optional)
- Status:
    - Open
    - In progress
    - Resolved
    - Closed
- Visibility:
    - Private (user + admin)
    - Public within organization

---

### 4.3 Request Lifecycle
- Created by user
- User can:
    - Comment on own requests
    - Approve resolution
- Admin can:
    - Comment
    - Change status
    - Close requests

---

### 4.4 Visibility
Users can see:
- All **their own requests**
- All **public requests** in organizations they belong to

---

## 5. Surveys (Voting)

### 5.1 Survey
A **Survey** belongs to one Organization and includes:
- Title
- Description
- Start date
- End date
- One or more questions

Only active surveys can be answered.

---

### 5.2 Questions
Each question includes:
- Title
- Description
- Attached files (PDF, images, documents)
- Answer type (initial MVP):
    - Yes / No

---

### 5.3 Voting Weight
- Each vote has a **weight**
- Weight is calculated as:
- Sum of owned square meters of the user within the organization
- Ownership across multiple apartments is aggregated
- Weight is organization-specific

---

### 5.4 Survey Results
Users can see:
- Number of participants
- Participation rate
- Yes / No distribution
- Weighted results (by square meters)

Admins can additionally see:
- Detailed participation data
- Optional mapping to apartments (configurable)

---

## 6. Multi-Organization Support

- One account → multiple organizations
- Clear organization context switching
- Unified dashboard showing:
- Active surveys
- Open requests
- Pending join approvals (for admins)

---

## 7. Non-Functional Considerations

- Role-based access control
- Audit logs (approvals, votes, status changes)
- Mobile-first UI
- Localization (UA / RU / EN)
- Export of survey results (PDF / CSV)
- Legal reliability for official OSBB decisions (future)

---

## 8. Open Questions

These decisions affect UX, architecture, and legal validity:

1. Can users comment on public requests created by others?
2. Are survey votes anonymous or named?
3. Can users change their vote before survey end?
4. What happens if ownership changes during an active survey?
5. Are admins allowed to vote?
6. Are survey results intended to be legally binding?
7. Is historical ownership tracking required?

---

## 9. MVP Scope (Suggested)

**Included:**
- Organizations
- User registration & approval
- Property registry
- Requests
- Yes/No surveys
- Weighted voting

**Later phases:**
- Payments
- Notifications
- Digital signatures
- Court-ready exports
- Mobile app