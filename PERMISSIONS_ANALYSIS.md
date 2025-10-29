# Permissions Analysis & Recommendations

## Current Implementation Review

After analyzing the voter implementations and related code, here's a summary of findings and recommendations.

---

## ✅ What's Working Well

### 1. Clear Separation of Concerns
- **TaskVoter** handles task-level permissions
- **SubTaskVoter** handles subtask-level permissions
- **TaskStatusService** handles state transitions and business logic
- Clean separation between authorization (voters) and business logic (service)

### 2. Comprehensive Role System
- Multiple roles (Member, Pilot, Inspector, Manager, Admin) with clear hierarchies
- Equipment-based access control (Aircraft vs. Facility)
- Privacy-based access control (Public vs. Private equipment)

### 3. Proper State Management
- Clear status flow: open → done → closed
- Rejection properly reverts to open state
- Contributions preserved after rejection for retry

### 4. Activity Logging
- All actions are properly logged with ActivityType
- Rejection reasons are captured
- Auto-validation is clearly logged

---

## ⚠️ Potential Issues & Inconsistencies

### Issue 1: Task Cancellation Status Check

**Location:** `TaskVoter::canCancel()`

**Current Code:**
```php
// Can only cancel open tasks
return $task->getStatus() === 'open';
```

**Problem:**
The method only checks for 'open' status, but according to the Task entity, there are 4 possible statuses: 'open', 'done', 'closed', 'cancelled'.

**Current Behavior:**
- ✅ Can cancel when status = 'open'
- ✅ Cannot cancel when status = 'cancelled' (correct)
- ❓ Can cancel when status = 'done' (is this intentional?)
- ✅ Cannot cancel when status = 'closed' (correct)

**Question:** Should tasks with status 'done' be cancellable? The current implementation allows it because it only prevents cancellation if status !== 'open'.

**Recommendation:**
```php
private function canCancel(Task $task, bool $isManager): bool
{
    if (!$isManager) {
        return false;
    }
    
    // Can only cancel tasks that are still open (not done, closed, or already cancelled)
    return $task->getStatus() === 'open';
}
```

**Current implementation seems correct** - tasks don't actually use 'done' status in practice (see Issue 2).

---

### Issue 2: Task 'done' Status is Never Used

**Location:** Task entity

**Observation:**
- Task entity defines status validation: `['open', 'done', 'closed', 'cancelled']`
- However, nowhere in the code does a task ever get status = 'done'
- Tasks go directly from 'open' to 'closed' or 'cancelled'
- Subtasks use 'done' status for inspection waiting, but tasks do not

**Current Flow:**
```
Task: open → closed (when all subtasks are closed/cancelled)
Task: open → cancelled (by manager)
```

**Questions:**
1. Is the 'done' status for tasks a planned future feature?
2. Should we remove it from validation to avoid confusion?

**Recommendation:**
Either:
- **Option A:** Remove 'done' from Task status validation since it's not used
- **Option B:** Document that it's reserved for future use

---

### Issue 3: Manager vs. Inspector Permissions Overlap

**Current Behavior:**
- Managers can **edit, delete, cancel** but **cannot inspect**
- Inspectors can **inspect** but **cannot edit, delete, cancel** (unless also manager)
- A user can have both roles simultaneously

**Potential Confusion:**
In some clubs, managers might expect to be able to approve inspections. The current system requires managers to explicitly also have the inspector role.

**Recommendation:**
This is actually a **good design** - it separates administrative powers from technical certification. However, it should be clearly documented in the UI when creating/editing memberships.

**UI Suggestion:**
When assigning roles, show:
- ☑️ Manager (can edit, delete, cancel tasks)
- ☑️ Inspector (can validate completed work)
- ℹ️ Note: Managers and Inspectors have different responsibilities. A user can have both roles.

---

### Issue 4: SubTask Edit Permission During 'done' Status

**Location:** `SubTaskVoter::canEdit()`

**Current Code:**
```php
// Cannot edit closed or cancelled subtasks
if ($subTask->getStatus() === 'closed' || $subTask->getStatus() === 'cancelled') {
    return false;
}
```

**Current Behavior:**
- ✅ Managers can edit subtasks with status = 'open'
- ✅ Managers can edit subtasks with status = 'done' (waiting for inspection)
- ❌ Managers cannot edit subtasks with status = 'closed'
- ❌ Managers cannot edit subtasks with status = 'cancelled'

**Question:** Should managers be able to edit subtasks that are in 'done' status (waiting for inspection)?

**Scenarios to consider:**

**Scenario A: Manager edits during inspection wait**
1. Member marks subtask as done (status → 'done')
2. Manager realizes there's an error in the subtask definition
3. Manager edits the subtask
4. Result: SubTask still shows as 'done', but definition has changed

**Potential Issue:**
If a manager edits a subtask (e.g., changes difficulty or requirements) while it's waiting for inspection, the inspector might be inspecting work based on old requirements.

**Recommendation:**
Consider one of these approaches:

**Option A: Allow editing but reset status**
```php
// In TaskStatusService or SubTaskController
if ($subTask->getStatus() === 'done' && manager edits) {
    // Reset to open so work can be re-evaluated
    $subTask->setStatus('open');
    $subTask->setDoneBy(null);
    $subTask->setDoneAt(null);
    // Log activity explaining why it was reset
}
```

**Option B: Prevent editing during inspection**
```php
private function canEdit(SubTask $subTask, bool $isManager): bool
{
    if (!$isManager) {
        return false;
    }
    
    // Cannot edit if waiting for inspection, closed, or cancelled
    if (in_array($subTask->getStatus(), ['done', 'closed', 'cancelled'])) {
        return false;
    }
    
    return true;
}
```

**Option C: Keep current behavior (document it)**
Document that managers can edit subtasks in 'done' status, but they should reject the inspection first if the work needs to be redone.

**Recommended:** Option B - Prevent editing once work is marked as done. If changes are needed, manager should:
1. Ask inspector to reject it (to return to 'open')
2. Then edit
3. Then member can redo it

---

### Issue 5: No Permission to "Undo" a SubTask

**Current Behavior:**
Once a subtask is marked as done, only an inspector can change its status (approve → closed, or reject → open).

**Scenario:**
1. Member accidentally marks subtask as done
2. Subtask doesn't require inspection, so it goes directly to 'closed'
3. No way to revert it except deleting and recreating

**Alternative Scenario:**
1. Inspector marks subtask as done (auto-approved to 'closed')
2. Inspector realizes they made a mistake
3. No way to reopen it

**Recommendation:**
Add an "UNDO" action for closed subtasks (manager-only):

```php
// In SubTaskVoter
public const UNDO = 'SUBTASK_UNDO';

private function canUndo(SubTask $subTask, bool $isManager): bool
{
    // Only managers can undo
    if (!$isManager) {
        return false;
    }
    
    // Can only undo if closed
    return $subTask->getStatus() === 'closed';
}
```

```php
// In TaskStatusService
public function handleSubTaskUndo(SubTask $subTask, User $manager, ?string $reason = null): void
{
    $subTask->setStatus('open');
    $subTask->setDoneBy(null);
    $subTask->setDoneAt(null);
    $subTask->setInspectedBy(null);
    $subTask->setInspectedAt(null);
    
    // Log activity
    $activity = new Activity();
    $activity->setTask($subTask->getTask());
    $activity->setSubTask($subTask);
    $activity->setType(ActivityType::UNDONE);
    $activity->setUser($manager);
    $activity->setMessage($reason);
    $this->entityManager->persist($activity);
    
    $this->entityManager->flush();
}
```

**Note:** This would require adding `UNDONE` to the ActivityType enum.

---

## 🎯 Recommended Actions

### High Priority

1. **Document Manager vs. Inspector distinction**
   - Update UI tooltips when assigning roles
   - Add help text explaining that managers cannot inspect unless also inspectors

2. **Consider preventing edits on 'done' subtasks** (Issue 4)
   - Implement Option B from Issue 4
   - Add flash message: "Cannot edit subtask while waiting for inspection. Ask an inspector to reject it first."

### Medium Priority

3. **Add UNDO functionality** (Issue 5)
   - Implement undo permission and handler
   - Add ActivityType::UNDONE
   - Add UI button for managers on closed subtasks

4. **Clarify Task 'done' status** (Issue 2)
   - Either remove from validation or document as reserved

### Low Priority

5. **Add more granular logging**
   - Log when managers edit subtasks in 'done' status
   - Log equipment type in activities for better audit trail

---

## 📋 Testing Recommendations

### Test Cases to Verify

#### Task Permissions
- [ ] Member cannot edit/delete tasks
- [ ] Manager can edit open tasks but not closed/cancelled tasks
- [ ] Pilot can work on aircraft tasks
- [ ] Non-pilot cannot work on aircraft tasks (unless manager/inspector)
- [ ] Private equipment tasks only visible to owners/managers/inspectors

#### SubTask Permissions
- [ ] Member can mark facility subtasks as done
- [ ] Member cannot mark aircraft subtasks as done (unless pilot)
- [ ] Inspector can approve/reject subtasks requiring inspection
- [ ] Manager cannot inspect (unless also inspector)
- [ ] Auto-approval works when inspector marks subtask as done

#### Inspection Flow
- [ ] SubTask without inspection requirement → immediately closed
- [ ] SubTask with inspection requirement (done by member) → status 'done'
- [ ] SubTask with inspection requirement (done by inspector) → auto-closed
- [ ] Rejection returns subtask to 'open' and clears done/inspect fields
- [ ] Contributions preserved after rejection

#### Edge Cases
- [ ] Cannot close task if any subtask is still open or done
- [ ] Cancelling task cascades to all open subtasks
- [ ] Cancelled subtasks don't count toward task progress
- [ ] Cannot create subtasks on closed/cancelled tasks

---

## 🔒 Security Considerations

### Current Security Posture: ✅ GOOD

1. **Proper Authorization Checks**
   - All controller actions use `#[IsGranted()]` attributes
   - Voters properly check user roles and entity states

2. **Cascading Permissions**
   - SubTask permissions inherit task permissions (via equipment checks)
   - Proper club membership verification

3. **State Validation**
   - Status transitions validated in TaskStatusService
   - Voters check current state before allowing actions

### Potential Improvements

1. **Rate Limiting**
   - Consider adding rate limiting on status changes to prevent abuse
   - Especially for undo/redo operations if implemented

2. **Audit Trail**
   - Current activity logging is good
   - Consider adding IP addresses or session IDs for security audits

3. **Concurrent Modification**
   - Consider adding optimistic locking to prevent race conditions
   - E.g., two inspectors trying to approve the same subtask simultaneously

---

## 📊 Permission Matrix Summary

### The "Four Gates" for SubTask Work

For a member to work on a subtask (DO action), they must pass 4 gates:

1. **Gate 1: Club Access**
   - Must be a member of the club
   
2. **Gate 2: Equipment Visibility**
   - Public: Anyone can see
   - Private: Must be owner, manager, or inspector

3. **Gate 3: Equipment Type**
   - Facility: Anyone can work
   - Aircraft: Must be pilot, manager, or inspector

4. **Gate 4: Status Check**
   - SubTask must be 'open'
   - Cannot work on done, closed, or cancelled subtasks

### The "Two Gates" for Inspection

For a member to inspect a subtask, they must pass 2 gates:

1. **Gate 1: Role**
   - Must be inspector (or admin)
   - Manager role is NOT sufficient

2. **Gate 2: SubTask State**
   - SubTask must require inspection
   - SubTask must be done (status = 'done')
   - SubTask must not already be inspected

---

## Conclusion

The current permission system is **well-designed and mostly working correctly**. The main areas for improvement are:

1. Better documentation of manager vs. inspector roles
2. Clarifying edit permissions on 'done' subtasks
3. Adding undo functionality for accidental completions

The security model is sound, with proper checks at multiple levels. The state machine is clear and well-implemented.

