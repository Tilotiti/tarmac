# Task/SubTask Permissions - Quick Reference

**Updated:** October 29, 2025 - New ownership-based permissions

## 🎯 Quick Decision Trees

### "Can I edit this task?"

```
START
  ↓
Did I create it? → YES → Is it closed/cancelled? → NO → ✅ ALLOWED
                                                  → YES → ❌ DENIED
  ↓ NO
Am I a manager? → YES → Is it closed/cancelled? → NO → ✅ ALLOWED
                                                → YES → ❌ DENIED
  ↓ NO
❌ DENIED
```

### "Can I cancel this task?"

```
START
  ↓
Is it open? → NO → ❌ DENIED (can only cancel open tasks)
  ↓ YES
Did I create it OR am I a manager? → YES → ✅ ALLOWED
                                   → NO → ❌ DENIED
```

### "Can I work on this subtask?"

```
START
  ↓
Am I a club member? → NO → ❌ DENIED
  ↓ YES
Is equipment private? → YES → Am I owner/manager/inspector? → NO → ❌ DENIED
  ↓ NO                                                         ↓ YES
Is equipment aircraft? → YES → Am I pilot/manager/inspector? → NO → ❌ DENIED
  ↓ NO                                                          ↓ YES
Is subtask status 'open'? → NO → ❌ DENIED
  ↓ YES
✅ ALLOWED - You can mark as done
```

### "Can I edit this subtask?"

```
START
  ↓
Is it open? → NO → ❌ DENIED (can only edit open subtasks)
  ↓ YES
Did I create it? → YES → ✅ ALLOWED
  ↓ NO
Am I a manager? → YES → ✅ ALLOWED
                → NO → ❌ DENIED
```

### "Can I cancel this subtask?"

```
START
  ↓
Is it open? → NO → ❌ DENIED (can only cancel open subtasks)
  ↓ YES
Did I create it OR am I a manager? → YES → ✅ ALLOWED
                                   → NO → ❌ DENIED
```

### "Can I inspect this subtask?"

```
START
  ↓
Am I an inspector (or admin)? → NO → ❌ DENIED
  ↓ YES
Does subtask require inspection? → NO → ❌ NOT NEEDED
  ↓ YES
Is subtask status 'done'? → NO → ❌ NOT READY
  ↓ YES
Is subtask already inspected? → YES → ❌ ALREADY DONE
  ↓ NO
✅ ALLOWED - You can approve or reject
```

---

## 📊 Actions at a Glance

| Symbol | Meaning |
|--------|---------|
| ✅ | Allowed |
| ❌ | Denied |
| 👤 | Creator only |
| 🔒 | Manager only |
| 🔍 | Inspector only |
| ✈️ | Pilot required for aircraft |
| 🏗️ | Available for facility |
| 🗑️ | Feature removed |

### Task Actions

| Action | Member (Own) | Member (Other's) | Manager | Inspector | Admin |
|--------|-------------|------------------|---------|-----------|-------|
| View (public facility) | ✅ | ✅ | ✅ | ✅ | ✅ |
| View (public aircraft) | ❌ | ❌ | ✅ | ✅ | ✅ |
| Edit (open/done) | 👤 | ❌ | 🔒 | 👤 | 🔒 |
| Edit (closed) | ❌ | ❌ | ❌ | ❌ | ✅ |
| ~~Delete~~ | 🗑️ | 🗑️ | 🗑️ | 🗑️ | 🗑️ |
| Comment | ✅ | ✅ | ✅ | ✅ | ✅ |
| Add SubTask (facility) | 🏗️ | 🏗️ | 🏗️ | 🏗️ | 🏗️ |
| Add SubTask (aircraft) | ❌ | ❌ | ✈️ | ✈️ | ✈️ |
| Close (facility) | 🏗️* | 🏗️* | 🏗️* | 🏗️* | 🏗️* |
| Close (aircraft) | ❌ | ❌ | ✈️* | ✈️* | ✈️* |
| Cancel (open) | 👤 | ❌ | 🔒 | 👤 | 🔒 |

*When all subtasks are closed/cancelled

### SubTask Actions

| Action | Member (Own) | Member (Other's) | Manager | Inspector | Admin |
|--------|-------------|------------------|---------|-----------|-------|
| View | Same as task | Same as task | Same as task | Same as task | Same as task |
| Edit (open) | 👤 | ❌ | 🔒 | 👤 | 🔒 |
| Edit (done/closed) | ❌ | ❌ | ❌ | ❌ | ❌ |
| ~~Delete~~ | 🗑️ | 🗑️ | 🗑️ | 🗑️ | 🗑️ |
| Comment | ✅ | ✅ | ✅ | ✅ | ✅ |
| Do (facility) | 🏗️ | 🏗️ | 🏗️ | 🏗️ | 🏗️ |
| Do (aircraft) | ❌ | ❌ | ✈️ | ✈️ | ✈️ |
| Inspect (status='done') | ❌ | ❌ | ❌ | 🔍 | 🔍 |
| Cancel (open) | 👤 | ❌ | 🔒 | 👤 | 🔒 |

---

## 🔄 Status Flow Diagrams

### SubTask Status Flow

```
┌──────────────────────────────────────────────────────────┐
│                      SubTask States                       │
└──────────────────────────────────────────────────────────┘

                    ┌─────────────┐
                    │    OPEN     │ ◄─────────┐
                    └─────┬───────┘           │
                          │                   │
                      [Member/Pilot           │
                       marks as done]         │
                          │                   │
            ┌─────────────┴─────────────┐     │
            │                           │     │
      [No inspection]           [Inspection   │
                                  required]   │
            │                           │     │
            │                ┌──────────┴──┐  │
            │                │   Inspector?  │  │
            │                └──┬────────┬──┘  │
            │                   │        │     │
            │               [Yes]      [No]    │
            │                   │        │     │
            ▼                   │        ▼     │
    ┌───────────┐               │   ┌────────┐ │
    │  CLOSED   │ ◄─────────────┘   │  DONE  │ │
    └───────────┘                   └───┬────┘ │
                                        │      │
                                   [Inspector] │
                                        │      │
                            ┌───────────┴───┐  │
                            │  Approve?     │  │
                            └───┬───────┬───┘  │
                                │       │      │
                              [Yes]   [No]     │
                                │       │      │
                                ▼       └──────┘
                        ┌───────────┐
                        │  CLOSED   │
                        └───────────┘

    ┌─────────────┐
    │  CANCELLED  │ ◄── [Manager/Creator cancels (only if OPEN)]
    └─────────────┘
```

### Task Status Flow

```
┌──────────────────────────────────────────────────────────┐
│                       Task States                         │
└──────────────────────────────────────────────────────────┘

            ┌─────────────┐
            │    OPEN     │
            └─────┬───────┘
                  │
        ┌─────────┴─────────┐
        │                   │
    [Manager or         [All subtasks
     Creator             closed/cancelled]
     cancels]               │
        │                   ▼
        ▼           ┌───────────┐
┌───────────┐       │  CLOSED   │
│ CANCELLED │       └───────────┘
└───────────┘

Note: Tasks never use 'DONE' status
```

---

## 🎨 Equipment Types Quick Ref

| Type | Who Can Work? | Icon | Examples |
|------|--------------|------|----------|
| **Aircraft** | Pilots, Managers, Inspectors | ✈️ | Gliders, Airplanes |
| **Facility** | All Members | 🏗️ | Hangars, Runways, Buildings |

---

## 🔐 Privacy Quick Ref

| Privacy | Who Can View? |
|---------|--------------|
| **Public** | All members (with equipment type restrictions) |
| **Private** | Owners, Managers, Inspectors only |

---

## 💡 Common Scenarios

### Scenario 1: "I created a task and want to edit it"
```
My Task: Open
Action: Edit
Required: Be the creator, task not closed/cancelled
Result: ✅ ALLOWED - You can edit your own tasks
```

### Scenario 2: "I created a subtask and want to cancel it"
```
My SubTask: Open
Action: Cancel
Required: Be the creator, subtask must be open
Result: ✅ ALLOWED - You can cancel your own open subtasks
Note: Once marked as "done", you can't cancel it anymore
```

### Scenario 3: "I marked a subtask as done by mistake"
```
Current Status: Done (waiting for inspection)
Options:
  - Ask an inspector to reject it
  - If manager: cancel and create a new one
  - If creator: cannot undo (status is not 'open')
```

### Scenario 4: "I want to edit someone else's task"
```
Not My Task: Open
Action: Edit
Required Role: Manager
Result: 
  - Member/Pilot: ❌ DENIED
  - Manager: ✅ ALLOWED
  - Admin: ✅ ALLOWED
```

### Scenario 5: "I want to fix the club hangar door"
```
Equipment: Hangar (Facility, Public)
Action: Create task → Add subtask → Mark as done
Required Role: Member ✅
Result: Subtask closes immediately (no inspection needed)
```

### Scenario 6: "I want to repair the club's glider wing"
```
Equipment: Glider (Aircraft, Public)
Action: Create task → Add subtask → Mark as done
Required Role: Pilot (or Inspector/Manager) ✈️
Result: Subtask goes to 'done' if inspection required
```

### Scenario 7: "I want to edit a subtask I created that's waiting for inspection"
```
My SubTask: Done (waiting for inspection)
Action: Edit
Result: ❌ DENIED - Can only edit OPEN subtasks
Solution: Ask inspector to reject it first
```

### Scenario 8: "I want to cancel a task I created that has subtasks done"
```
My Task: Open, but has done/closed subtasks
Action: Cancel
Result: ✅ ALLOWED - Task and all OPEN subtasks cancelled
Note: Done/closed subtasks remain unchanged
```

---

## 🚨 What You CANNOT Do

### All Members
- ❌ Edit tasks/subtasks you didn't create (unless manager)
- ❌ Edit anything that's not open
- ❌ Cancel anything that's not open
- ❌ Delete tasks or subtasks (feature removed)

### Members (Non-Pilot)
- ❌ Work on aircraft equipment
- ❌ View aircraft tasks (unless equipment owner)

### Pilots
- ❌ Edit or cancel other people's tasks/subtasks (unless manager)
- ❌ Inspect work (unless also inspector)

### Inspectors
- ❌ Edit or cancel other people's tasks/subtasks (unless also manager)
- ❌ Approve inspections if status is not 'done'

### Managers
- ❌ Inspect work (unless also inspector)
- ❌ Edit closed or cancelled tasks
- ❌ Edit done/closed subtasks
- ❌ Cancel non-open tasks/subtasks

### Everyone (Including Admin)
- ❌ Edit closed/cancelled subtasks
- ❌ Cancel done/closed/cancelled subtasks
- ❌ Inspect subtasks that don't require inspection
- ❌ Inspect subtasks that are not in 'done' status

---

## 🎓 Role Combinations

Common real-world combinations:

| Combination | Common For | Powers | Restrictions |
|-------------|-----------|--------|--------------|
| Member only | New members | Basic participation, facility work, edit own items | No aircraft, no manage, no inspect |
| Pilot | Certified pilots | + Aircraft work, edit own items | No manage, no inspect |
| Pilot + Inspector | Experienced pilots | + Aircraft work + Inspections | Cannot edit others' items |
| Manager | Club officers | Edit/cancel all open items (but not inspect) | Cannot inspect |
| Manager + Inspector | Technical directors | Full control + inspections | - |
| Admin | System administrators | Manager + Inspector combined | Still bound by status rules |

---

## 🆕 What's New (October 29, 2025)

### Key Changes

1. **Ownership-Based Permissions**
   - Tasks and SubTasks now track creator via `createdBy` field
   - Members can edit and cancel their own open tasks/subtasks
   - Managers can still edit/cancel any open items

2. **DELETE Feature Removed**
   - Both TASK_DELETE and SUBTASK_DELETE removed
   - Use CANCEL instead - it's safer and maintains audit trail

3. **Admin Permissions Clarified**
   - Admin = Manager + Inspector permissions
   - Admin follows same status rules (e.g., can't approve if status ≠ 'done')

4. **Cancel Restrictions**
   - Can ONLY cancel items with status 'open'
   - Cannot cancel done/closed/cancelled items
   - Owners can cancel their own open items

5. **Edit Restrictions**
   - Can ONLY edit open items (except admin can edit closed tasks)
   - Cannot edit done/closed/cancelled subtasks (even as admin)
   - Owners can edit their own open items

### Migration Required

```bash
symfony console doctrine:migrations:migrate
```

Adds `createdBy` and `createdAt` to `sub_task` table.

---

## 📞 Quick Help

**"I can't edit my own task"**
- Check: Is it closed or cancelled? You can only edit open/done tasks

**"I can't cancel my subtask"**
- Check: Is it still open? You can only cancel open subtasks
- Check: Did you create it? Only creators and managers can cancel

**"I marked a subtask as done and want to undo it"**
- If inspection required: Ask an inspector to reject it
- If no inspection: Cannot undo (status is 'closed'), ask manager to cancel

**"I can't mark a subtask as done"**
- Check: Is equipment aircraft? You need pilot role
- Check: Is subtask status 'open'? Cannot work on done/closed/cancelled

**"I can't see an aircraft task"**
- Check: Do you have pilot role?
- Check: Is equipment private? You need to be an owner

**"I can't approve an inspection"**
- Check: Do you have inspector role? (Manager is not enough)
- Check: Is subtask status 'done'? (Not open or closed)
- Check: Does subtask require inspection?

**"I can't edit someone else's task"**
- Check: Do you have manager role?
- Check: Is task still open or done? (Cannot edit closed/cancelled)

**"I can't cancel someone else's subtask"**
- Check: Do you have manager role?
- Check: Is subtask status 'open'? (Can only cancel open items)

---

## 🎯 Status Rules Summary

| Status | Can Edit? | Can Cancel? | Can Do? | Can Inspect? |
|--------|-----------|-------------|---------|--------------|
| **OPEN** | ✅ Creator or Manager | ✅ Creator or Manager | ✅ Authorized members | ❌ Not ready |
| **DONE** | ❌ Nobody (except task as admin) | ❌ Nobody | ❌ Already done | ✅ Inspector only |
| **CLOSED** | ❌ Nobody (except task as admin) | ❌ Nobody | ❌ Already closed | ❌ Already inspected |
| **CANCELLED** | ❌ Nobody (except task as admin) | ❌ Already cancelled | ❌ Cancelled | ❌ N/A |

---

## 📚 Full Documentation

For complete details, see:
- `PERMISSIONS_MATRIX.md` - Complete permission tables and workflows
- `PERMISSIONS_ANALYSIS.md` - Implementation analysis and recommendations
- `src/Security/Voter/TaskVoter.php` - Task permissions implementation
- `src/Security/Voter/SubTaskVoter.php` - SubTask permissions implementation
- `src/Service/Maintenance/TaskStatusService.php` - State transitions
- `migrations/Version20251029112734.php` - Database changes
