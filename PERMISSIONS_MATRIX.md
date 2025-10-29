# Task & SubTask Permissions Matrix

## Overview

This document outlines the complete permission system for maintenance tasks and subtasks in the Tarmac application, based on the current voter implementation.

**Last Updated:** October 29, 2025 - Reflects new ownership-based permissions

## Entity Statuses

### Task Statuses
- **open**: Task is active and can be worked on
- **done**: Not directly used (tasks don't have a "done" state)
- **closed**: All subtasks are completed/cancelled, task is finalized
- **cancelled**: Task was cancelled by a manager or owner

### SubTask Statuses
- **open**: SubTask is available to work on
- **done**: SubTask is completed but waiting for inspection (if required)
- **closed**: SubTask is fully completed and approved (if inspection required)
- **cancelled**: SubTask was cancelled

### SubTask State Flow

```
open → [user marks as done] → done (if inspection required) → [inspector approves] → closed
                            → closed (if no inspection required)
                            → closed (if done by inspector and inspection required)

done → [inspector rejects] → open (returns to open state, can be redone)

open → [manager/owner cancels] → cancelled
```

## Member Roles

1. **Member**: Basic club member (non-pilot)
2. **Pilot (Pilote)**: Member with pilot qualification
3. **Inspector**: Member qualified to inspect work
4. **Manager**: Club manager (has all member/pilot/inspector permissions)
5. **Admin**: System admin (has all permissions, same rights as managers + inspectors combined)

## Equipment Types

- **Aircraft** (Glider, Airplane): Requires pilot qualification to work on
- **Facility** (Infrastructure): Any member can work on

## Equipment Visibility

- **Public**: All members can view tasks
- **Private**: Only managers, inspectors, and owners can view tasks

---

## TASK PERMISSIONS

### ACTION: VIEW (TASK_VIEW)

| Role | Aircraft (Public) | Aircraft (Private) | Facility (Public) | Facility (Private) |
|------|-------------------|-------------------|-------------------|-------------------|
| Member | ❌ | ❌ | ✅ | Only if owner |
| Pilot | ✅ | Only if owner | ✅ | Only if owner |
| Inspector | ✅ | ✅ | ✅ | ✅ |
| Manager | ✅ | ✅ | ✅ | ✅ |
| Admin | ✅ | ✅ | ✅ | ✅ |

**Rules:**
- Aircraft tasks: Only pilots, inspectors, and managers can view
- Private equipment: Only managers, inspectors, and equipment owners can view
- Public facility: Any member can view

---

### ACTION: EDIT (TASK_EDIT)

| Role | Own Task (Open) | Own Task (Done) | Own Task (Closed/Cancelled) | Other's Task |
|------|-----------------|-----------------|----------------------------|--------------|
| Member | ✅ | ✅ | ❌ | ❌ |
| Pilot | ✅ | ✅ | ❌ | ❌ |
| Inspector | ✅ | ✅ | ❌ | ❌ |
| Manager | ✅ | ✅ | ❌ | ✅ (if not closed/cancelled) |
| Admin | ✅ | ✅ | ✅ | ✅ |

**Rules:**
- **Any member can edit their own tasks** until the task is closed or cancelled
- **Managers can edit any task** that is not closed or cancelled
- **Cannot edit closed or cancelled tasks** (except admins)
- Admin has same rights as managers (all non-closed tasks)

---

### ACTION: DELETE (TASK_DELETE)

**REMOVED** - This feature has been removed. Use CANCEL instead.

---

### ACTION: COMMENT (TASK_COMMENT)

| Role | Permission |
|------|-----------|
| All club members | ✅ |

**Rules:**
- Any member with access to the club can comment on tasks they can view

---

### ACTION: CREATE SUBTASK (TASK_CREATE_SUBTASK)

| Role | Aircraft | Facility | Status Restrictions |
|------|----------|----------|-------------------|
| Member | ❌ | ✅ | Cannot if task is done, closed, or cancelled |
| Pilot | ✅ | ✅ | Cannot if task is done, closed, or cancelled |
| Inspector | ✅ | ✅ | Cannot if task is done, closed, or cancelled |
| Manager | ✅ | ✅ | Cannot if task is done, closed, or cancelled |
| Admin | ✅ | ✅ | ✅ (same as managers) |

**Rules:**
- Aircraft: Only pilots, inspectors, or managers can create subtasks
- Facility: Any member can create subtasks
- Cannot create subtasks if task is done, closed, or cancelled
- Admin has same rights as managers

---

### ACTION: CLOSE (TASK_CLOSE)

| Role | Aircraft | Facility | Additional Requirements |
|------|----------|----------|------------------------|
| Member | ❌ | ✅ | All subtasks must be closed/cancelled |
| Pilot | ✅ | ✅ | All subtasks must be closed/cancelled |
| Inspector | ✅ | ✅ | All subtasks must be closed/cancelled |
| Manager | ✅ | ✅ | All subtasks must be closed/cancelled |
| Admin | ✅ | ✅ | All subtasks must be closed/cancelled |

**Rules:**
- Task must be in "open" status
- All subtasks must be closed or cancelled
- Must have at least one subtask
- Aircraft: Only pilots, inspectors, or managers can close
- Facility: Any member can close

---

### ACTION: CANCEL (TASK_CANCEL)

| Role | Own Task (Open) | Other's Task (Open) | Any Non-Open Task |
|------|-----------------|---------------------|-------------------|
| Member | ✅ | ❌ | ❌ |
| Pilot | ✅ | ❌ | ❌ |
| Inspector | ✅ | ❌ | ❌ |
| Manager | ✅ | ✅ | ❌ |
| Admin | ✅ | ✅ | ❌ |

**Rules:**
- **Any member can cancel their own open tasks**
- **Managers can cancel any open task**
- **Can only cancel tasks with status "open"** (not done, closed, or already cancelled)
- Cancelling a task cascades: all open subtasks are also cancelled
- Admin has same rights as managers

---

## SUBTASK PERMISSIONS

### ACTION: VIEW (SUBTASK_VIEW)

Same rules as TASK_VIEW (inherited from parent task).

---

### ACTION: EDIT (SUBTASK_EDIT)

| Role | Own SubTask (Open) | Own SubTask (Done/Closed/Cancelled) | Other's SubTask (Open) | Other's SubTask (Non-Open) |
|------|--------------------|-------------------------------------|------------------------|---------------------------|
| Member | ✅ | ❌ | ❌ | ❌ |
| Pilot | ✅ | ❌ | ❌ | ❌ |
| Inspector | ✅ | ❌ | ❌ | ❌ |
| Manager | ✅ | ❌ | ✅ | ❌ |
| Admin | ✅ | ❌ | ✅ | ❌ |

**Rules:**
- **Any member can edit their own open subtasks**
- **Managers can edit any open subtask**
- **Can only edit subtasks with status "open"**
- Cannot edit done, closed, or cancelled subtasks
- Admin has same rights as managers

---

### ACTION: DELETE (SUBTASK_DELETE)

**REMOVED** - This feature has been removed. Use CANCEL instead.

---

### ACTION: COMMENT (SUBTASK_COMMENT)

| Role | Permission |
|------|-----------|
| All club members | ✅ |

**Rules:**
- Any member with access to the club can comment on subtasks they can view

---

### ACTION: DO (SUBTASK_DO) - Mark as Done/Complete

| Role | Aircraft | Facility | Status Restrictions |
|------|----------|----------|-------------------|
| Member | ❌ | ✅ | Cannot if cancelled, closed, or already done |
| Pilot | ✅ | ✅ | Cannot if cancelled, closed, or already done |
| Inspector | ✅ | ✅ | Cannot if cancelled, closed, or already done |
| Manager | ✅ | ✅ | Cannot if cancelled, closed, or already done |
| Admin | ✅ | ✅ | ✅ (same as managers) |

**Auto-completion logic:**
- If subtask **does NOT require inspection**: Status → `closed` immediately
- If subtask **requires inspection** AND done by **inspector**: Status → `closed` (auto-approved)
- If subtask **requires inspection** AND done by **non-inspector**: Status → `done` (waits for inspection)

**Rules:**
- Aircraft: Only pilots, inspectors, or managers can mark as done
- Facility: Any member can mark as done
- Cannot mark as done if subtask is cancelled, closed, or already done
- Admin has same rights as managers

---

### ACTION: INSPECT (SUBTASK_INSPECT) - Approve or Reject

#### Approve (SUBTASK_INSPECT_APPROVE)

| Role | Can Inspect? | SubTask Requirements |
|------|-------------|---------------------|
| Member | ❌ | - |
| Pilot | ❌ | - |
| Inspector | ✅ | Must require inspection, be done, not yet inspected, status = 'done' |
| Manager | ❌* | - |
| Admin | ✅ | Must require inspection, be done, not yet inspected, status = 'done' |

*Note: Managers are not automatically inspectors unless they also have the inspector role.

**Rules:**
- **Only inspectors can approve/reject inspections**
- **Admin has same rights as inspectors** - cannot approve if status is not 'done'
- SubTask must require inspection
- SubTask must be marked as done (status = 'done')
- SubTask must not already be inspected

**On Approval:**
- Status → `closed`
- `inspectedBy` and `inspectedAt` are set
- Activity logged: `INSPECTED_APPROVED`

#### Reject (SUBTASK_INSPECT_REJECT)

Same permissions as Approve.

**On Rejection:**
- Status → `open` (returns to open state)
- `doneBy`, `doneAt`, `inspectedBy`, `inspectedAt` are all cleared
- **Contributions are preserved** (so they can be pre-filled when re-doing)
- Activity logged: `INSPECTED_REJECTED` with reason
- Rejection reason is **required**

---

### ACTION: CANCEL (SUBTASK_CANCEL)

| Role | Own SubTask (Open) | Own SubTask (Done/Closed) | Other's SubTask (Open) | Other's SubTask (Non-Open) |
|------|--------------------|---------------------------|------------------------|---------------------------|
| Member | ✅ | ❌ | ❌ | ❌ |
| Pilot | ✅ | ❌ | ❌ | ❌ |
| Inspector | ✅ | ❌ | ❌ | ❌ |
| Manager | ✅ | ❌ | ✅ | ❌ |
| Admin | ✅ | ❌ | ✅ | ❌ |

**Rules:**
- **Any member can cancel their own open subtasks**
- **Managers can cancel any open subtask**
- **Can only cancel subtasks with status "open"** (not done, closed, or cancelled)
- Cancellation reason is optional
- Admin has same rights as managers

---

## Key Workflows

### Workflow 1: Simple SubTask (No Inspection Required)

```
1. Member/Pilot creates subtask with requiresInspection = false
2. Authorized member marks subtask as done
   → Status automatically becomes 'closed'
3. When all subtasks closed → Task can be closed
```

### Workflow 2: SubTask Requiring Inspection (Done by Regular Member)

```
1. Member/Pilot creates subtask with requiresInspection = true
2. Authorized member marks subtask as done
   → Status becomes 'done' (waiting for approval)
3. Inspector approves
   → Status becomes 'closed'
4. When all subtasks closed → Task can be closed
```

### Workflow 3: SubTask Requiring Inspection (Done by Inspector)

```
1. Inspector creates subtask with requiresInspection = true
2. Inspector marks subtask as done
   → Status automatically becomes 'closed' (auto-approved)
   → Activity logged: "Auto-validé (membre qualifié)"
3. When all subtasks closed → Task can be closed
```

### Workflow 4: SubTask Rejected by Inspector

```
1. Member marks subtask as done
   → Status becomes 'done'
2. Inspector rejects with reason
   → Status returns to 'open'
   → All done/inspection fields cleared
   → Contributions preserved
3. Member can re-do the subtask
   → Form pre-filled with previous contribution data
4. Inspector can approve on second attempt
   → Status becomes 'closed'
```

### Workflow 5: Task Cancellation

```
1. Manager or task creator cancels task
   → Task status → 'cancelled'
   → All open subtasks → 'cancelled'
   → Done/closed subtasks remain unchanged
```

### Workflow 6: Member Edits Own Task/SubTask

```
1. Member creates a task and subtasks
2. Member can edit task and their own subtasks while open
3. Once member marks subtask as done, they can no longer edit it
4. Manager can still edit the open task but not done/closed subtasks
```

---

## Special Cases & Edge Cases

### Private Equipment Ownership
- Even regular members can view/work on tasks for private equipment if they are listed as owners
- This overrides pilot requirements for aircraft

### Managers vs. Inspectors
- Managers can edit/delete but **cannot** inspect work unless they also have the inspector role
- Inspectors can inspect but **cannot** edit/delete unless they also have the manager role
- In the UI, a user can have multiple roles simultaneously

### Admin Permissions
- **Admin = Manager + Inspector permissions**
- Admin has same rights as managers for edit/cancel/create actions
- Admin has same rights as inspectors for inspection actions
- Admin still subject to status rules (e.g., cannot approve if status is not 'done')

### Task/SubTask Ownership
- Tasks and SubTasks track who created them via `createdBy` field
- Members can edit and cancel their own tasks/subtasks when open
- Ownership does not override status rules (cannot edit closed items)

### Contributions After Rejection
- When a subtask is rejected, contributions are preserved
- This allows the form to be pre-filled when the member tries again
- If different contributors are selected on re-submission, old contributions are deleted

### Task Progress Calculation
- Progress = (count of closed subtasks / total subtasks) * 100
- Cancelled subtasks are NOT counted as closed for progress
- Only subtasks with status = 'closed' count toward progress

### Equipment Type Checks
- Always evaluated: `equipment.getType().isAircraft()`
- Aircraft includes: GLIDER and AIRPLANE
- Facility: FACILITY only

---

## Summary Table: Who Can Do What?

| Action | Member (Own) | Member (Other's) | Pilot (Own) | Pilot (Other's) | Inspector | Manager | Admin |
|--------|-------------|------------------|-------------|-----------------|-----------|---------|-------|
| **TASK** |
| View (facility, public) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| View (aircraft, public) | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |
| View (private, owner) | ✅ | ❌ | ✅ | ❌ | ✅ | ✅ | ✅ |
| Edit (open) | ✅ | ❌ | ✅ | ❌ | ✅ (own) | ✅ | ✅ |
| Edit (done) | ✅ | ❌ | ✅ | ❌ | ✅ (own) | ✅ | ✅ |
| Edit (closed/cancelled) | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| ~~Delete~~ | **REMOVED** | **REMOVED** | **REMOVED** | **REMOVED** | **REMOVED** | **REMOVED** | **REMOVED** |
| Comment | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Create SubTask (facility) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Create SubTask (aircraft) | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Close (facility) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Close (aircraft) | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Cancel (open) | ✅ | ❌ | ✅ | ❌ | ✅ (own) | ✅ | ✅ |
| **SUBTASK** |
| View | Same as task | Same as task | Same as task | Same as task | Same as task | Same as task | Same as task |
| Edit (open) | ✅ | ❌ | ✅ | ❌ | ✅ (own) | ✅ | ✅ |
| Edit (done/closed) | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| ~~Delete~~ | **REMOVED** | **REMOVED** | **REMOVED** | **REMOVED** | **REMOVED** | **REMOVED** | **REMOVED** |
| Comment | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Do (facility) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Do (aircraft) | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Inspect (status='done') | ❌ | ❌ | ❌ | ❌ | ✅ | ❌* | ✅ |
| Cancel (open) | ✅ | ❌ | ✅ | ❌ | ✅ (own) | ✅ | ✅ |

*Unless manager also has inspector role

---

## Voter Classes

- **TaskVoter**: `/src/Security/Voter/TaskVoter.php`
  - Attributes: VIEW, EDIT, COMMENT, CREATE_SUBTASK, CLOSE, CANCEL
  - ~~Removed: DELETE~~

- **SubTaskVoter**: `/src/Security/Voter/SubTaskVoter.php`
  - Attributes: VIEW, EDIT, COMMENT, DO, INSPECT, CANCEL
  - ~~Removed: DELETE~~

---

## Related Services

- **TaskStatusService**: `/src/Service/Maintenance/TaskStatusService.php`
  - Handles state transitions and validation
  - Methods:
    - `handleSubTaskDone()` - Mark subtask as done
    - `handleSubTaskInspectApprove()` - Approve inspection
    - `handleSubTaskInspectReject()` - Reject inspection and revert to open
    - `handleTaskClose()` - Close task
    - `handleCancelTask()` - Cancel task and open subtasks
    - `handleCancelSubTask()` - Cancel individual subtask
    - `canCloseTask()` - Check if task can be closed
    - `canCloseSubTask()` - Check if subtask can be closed

---

## Entity Changes

### SubTask Entity
- **Added Fields:**
  - `createdBy` (User): Tracks who created the subtask
  - `createdAt` (DateTimeImmutable): Timestamp of creation

### Task Entity
- **Existing Fields:**
  - `createdBy` (User): Already tracks who created the task
  - `createdAt` (DateTimeImmutable): Already has timestamp

---

## Migration

**File:** `migrations/Version20251029112734.php`

Adds `createdBy` and `createdAt` fields to the `sub_task` table:
- `created_by_id`: Foreign key to user table (nullable, SET NULL on delete)
- `created_at`: Timestamp with timezone (NOT NULL, defaults to NOW() for existing records)
