Here's a comprehensive guide to prompt Cursor AI to build the Weekly Plan interface:

---

## CURSOR AI PROMPT GUIDE - Weekly Plan Module

### **Initial Setup Prompt**

```
I need to create a Weekly Plan management system for staff members. Here's what I need:

CONTEXT:
- Staff members currently send weekly plans via email using a Word template
- Multiple managers need to review these plans
- We need a structured web interface that matches the existing template format

TEMPLATE STRUCTURE (6 main sections):
1. Header Info: Week number, dates, created by
2. Key Objectives for the Week (table format)
3. Planned Activities (table format)
4. Key Meetings/Engagement (table format)
5. Support Needed/Challenges/Risk Mitigation (table format)
6. Remarks (table format)
7. Expected Weekly Budget (table format)

TECH STACK:
- Backend: Laravel
- Frontend: Blade templates with Tailwind CSS
- Database: MySQL

Please help me build this step by step, starting with the database schema.
```

---

### **Step 1: Database Schema Prompt**

```
Create a database schema for the Weekly Plan system with these requirements:

TABLES NEEDED:
1. weekly_plans (main table)
   - id, user_id, department, week_number, start_date, end_date, status (draft/submitted/approved/rejected), created_at, updated_at

2. weekly_plan_objectives
   - id, weekly_plan_id, objective, description, responsible_person, status_note, reflection_progress

3. weekly_plan_activities
   - id, weekly_plan_id, day, activity, time, location, lead_staff_team, notes_inclusion

4. weekly_plan_meetings
   - id, weekly_plan_id, date, meeting_engagement, participants, location, reactions_notes

5. weekly_plan_challenges
   - id, weekly_plan_id, challenge_issue, support_needed, responsible_person, mitigation_next_step

6. weekly_plan_remarks
   - id, weekly_plan_id, activity_focus_area, notes_remarks

7. weekly_plan_budget
   - id, weekly_plan_id, budget_item_activity, description_purpose, amount_tzs, responsible_person

Please generate:
1. Migration files for all tables
2. Laravel models with relationships
3. Make sure all foreign keys and cascading deletes are properly set up
```

---

### **Step 2: Model & Relationships Prompt**

```
Create Laravel models for the Weekly Plan system:

REQUIREMENTS:
1. WeeklyPlan model with hasMany relationships to all child tables
2. Each child model (Objective, Activity, Meeting, Challenge, Remark, Budget) with belongsTo relationship to WeeklyPlan
3. Add fillable properties for mass assignment
4. Add any necessary accessors/mutators for dates
5. Include soft deletes where appropriate

Also create a WeeklyPlanService class to handle business logic for:
- Creating new weekly plans
- Updating plans
- Submitting plans for review
- Approving/rejecting plans
```

---

### **Step 3: Routes & Controller Prompt**

```
Create routes and controller for Weekly Plan management:

ROUTES NEEDED:
Staff routes (authenticated users):
- GET /weekly-plans - List all my weekly plans
- GET /weekly-plans/create - Show create form
- POST /weekly-plans - Store new plan
- GET /weekly-plans/{id} - View specific plan
- GET /weekly-plans/{id}/edit - Edit plan (only if draft)
- PUT /weekly-plans/{id} - Update plan
- DELETE /weekly-plans/{id} - Delete plan
- POST /weekly-plans/{id}/submit - Submit for review

Manager routes (reviewers):
- GET /weekly-plans/review - List plans pending review
- POST /weekly-plans/{id}/approve - Approve plan
- POST /weekly-plans/{id}/reject - Reject plan with comments

Create WeeklyPlanController with all necessary methods.
Use Laravel's authorization policies to ensure users can only edit their own plans.
```

---

### **Step 4: Create Form Interface Prompt**

```
Create a user-friendly form interface for creating/editing Weekly Plans that matches the Word template layout:

REQUIREMENTS:
1. Use Blade components and Tailwind CSS
2. Create a multi-step form OR single scrollable page with clear sections
3. Each section should match the template structure exactly

FORM SECTIONS:

HEADER (auto-filled where possible):
- Week number (input)
- Start date and End date (date pickers)
- Created by (auto-filled from auth user)
- Department (dropdown or input)

SECTION 1: Key Objectives (dynamic table)
- Add/Remove rows dynamically with JavaScript
- Columns: Objective, Description, Responsible Person, Status Note, Reflection/Progress
- At least 1 row required

SECTION 2: Planned Activities (dynamic table)
- Columns: Day (dropdown: Mon-Sat), Activity, Time, Location, Lead Staff/Team, Notes/Inclusion
- Add/Remove rows

SECTION 3: Key Meetings/Engagement (dynamic table)
- Columns: Date, Meeting/Engagement, Participants, Location, Reactions/Notes
- Add/Remove rows

SECTION 4: Support Needed/Challenges (dynamic table)
- Columns: Challenge/Issue, Support Needed, Responsible Person, Mitigation/Next Step
- Add/Remove rows

SECTION 5: Remarks (dynamic table)
- Columns: Activity/Focus Area, Notes/Remarks
- Add/Remove rows

SECTION 6: Budget (optional, dynamic table)
- Columns: Budget Item/Activity, Description/Purpose, Amount (TZS), Responsible Person
- Add/Remove rows

FORM ACTIONS:
- Save as Draft button
- Submit for Review button
- Cancel button

Include proper validation on both client-side (JavaScript) and server-side (Laravel validation rules).
```

---

### **Step 5: View/Display Page Prompt**

```
Create a view page to display a submitted Weekly Plan in a clean, readable format:

REQUIREMENTS:
1. Display all sections in a format similar to the Word template
2. Use tables with proper styling (Tailwind CSS)
3. Show submission status badge (Draft/Submitted/Approved/Rejected)
4. Add action buttons based on user role and plan status:
   - Edit (if user owns plan and status is draft)
   - Submit (if status is draft)
   - Approve/Reject (if user is manager and status is submitted)
   - Export to PDF (optional but nice to have)

LAYOUT:
- Header with week info and status
- Each section displayed in clean table format
- Comments section if plan was rejected
- Action buttons at top and bottom
```

---

### **Step 6: List/Dashboard Prompt**

```
Create dashboard views for both staff and managers:

STAFF DASHBOARD (/weekly-plans):
- List of my weekly plans (table view)
- Columns: Week, Dates, Department, Status, Submitted Date, Actions
- Filter by status (All/Draft/Submitted/Approved/Rejected)
- Search by week number or date range
- "Create New Plan" button
- Color-coded status badges

MANAGER DASHBOARD (/weekly-plans/review):
- List of plans pending review from all staff
- Columns: Staff Name, Department, Week, Dates, Submitted Date, Actions
- Quick approve/reject buttons
- Filter by department
- View details button

Use DataTables or similar for sorting, searching, and pagination.
```

---

### **Step 7: Validation & Business Logic Prompt**

```
Add comprehensive validation and business logic:

VALIDATION RULES:
1. At least 1 objective is required
2. At least 1 planned activity is required
3. Date validation: end_date must be after start_date
4. Status transitions: draft → submitted → approved/rejected
5. Only plan owner can edit draft plans
6. Only managers can approve/reject plans

BUSINESS LOGIC:
1. Auto-fill "Created By" from authenticated user
2. Auto-calculate week number from dates if possible
3. Send email notification when:
   - Plan is submitted (notify managers)
   - Plan is approved/rejected (notify staff member)
4. Prevent editing of submitted/approved plans
5. Allow copying previous week's plan as template

Create FormRequest classes for validation and Policy classes for authorization.
```

---

### **Step 8: Enhancement Prompts (Optional)**

```
ENHANCEMENT 1 - Copy Previous Week:
Add a "Copy from Previous Week" feature that:
- Lists user's previous weekly plans
- Allows selecting one to use as template
- Pre-fills all sections with previous data
- User can then modify as needed

ENHANCEMENT 2 - PDF Export:
Add PDF export functionality using Laravel DomPDF or similar:
- Export button on view page
- Generate PDF matching the Word template format
- Include all sections and proper formatting

ENHANCEMENT 3 - Comments/Feedback:
Add a commenting system where:
- Managers can leave comments on specific sections
- Staff can see feedback history
- Comments are timestamped and attributed

ENHANCEMENT 4 - Templates:
Allow admins to create department-specific templates with:
- Pre-filled common activities
- Standard objectives
- Typical meetings
```

---

### **Testing Prompt**

```
Create tests for the Weekly Plan system:

1. Feature tests for:
   - Creating a weekly plan
   - Updating a plan
   - Submitting for review
   - Approving/rejecting plans
   - Authorization (users can't edit others' plans)

2. Validation tests for:
   - Required fields
   - Date validations
   - Status transition rules

3. Policy tests for authorization rules

Use Laravel's testing framework with PHPUnit.
```

---

## **Usage Tips:**

1. **Start with Step 1** and work sequentially
2. **Review each output** before moving to the next step
3. **Customize prompts** based on your specific needs
4. **Test frequently** as you build each component
5. **Use "Explain this code"** in Cursor if you don't understand something

This structured approach will help Cursor understand your requirements and generate appropriate code for each component!