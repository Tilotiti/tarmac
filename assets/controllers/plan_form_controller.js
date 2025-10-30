import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tasksContainer', 'taskTemplate', 'addTaskButton'];

    connect() {
        this.taskCount = 0;
        this.subtaskCounts = new Map(); // Track subtask count per task

        // Count existing tasks on page load
        this.updateTaskCount();
    }

    updateTaskCount() {
        const existingTasks = this.tasksContainerTarget.querySelectorAll('[data-task-index]');
        this.taskCount = existingTasks.length;

        // Initialize subtask counts for existing tasks
        existingTasks.forEach(taskEl => {
            const taskIndex = parseInt(taskEl.dataset.taskIndex);
            const subtasks = taskEl.querySelectorAll('[data-subtask-index]');
            this.subtaskCounts.set(taskIndex, subtasks.length);
        });
    }

    addTask(event) {
        event.preventDefault();

        const taskIndex = this.taskCount;
        const template = this.taskTemplateTarget.innerHTML;

        // Replace task index placeholders
        let newTask = template.replace(/__TASK_INDEX__/g, taskIndex + 1); // 1-based for display
        newTask = newTask.replace(/__name__/g, taskIndex);

        // Create temporary element to parse HTML
        const temp = document.createElement('div');
        temp.innerHTML = newTask;
        const taskElement = temp.firstElementChild;

        // Set task index as data attribute for tracking
        taskElement.dataset.taskIndex = taskIndex;

        // Append to container
        this.tasksContainerTarget.appendChild(taskElement);

        // Initialize subtask count for this task
        this.subtaskCounts.set(taskIndex, 0);

        // Increment task counter
        this.taskCount++;

        // Hide placeholder if exists
        this.updatePlaceholder();

        // Automatically create the first subtask for this new task
        this.addSubTaskToTask(taskIndex);
    }

    addSubTaskToTask(taskIndex) {
        // Find the task card by index
        const taskCard = this.tasksContainerTarget.querySelector(`[data-task-index="${taskIndex}"]`);
        if (!taskCard) {
            console.error(`Task with index ${taskIndex} not found`);
            return;
        }

        // Get subtask container and template from this specific task
        const subtaskContainer = taskCard.querySelector('[data-subtask-container]');
        const subtaskTemplate = taskCard.querySelector('[data-subtask-template]');

        if (!subtaskContainer || !subtaskTemplate) {
            console.error('Subtask container or template not found');
            return;
        }

        // Get current subtask count for this task
        const subtaskIndex = this.subtaskCounts.get(taskIndex) || 0;

        // Get template HTML
        let template = subtaskTemplate.innerHTML;

        // Replace placeholders for display
        template = template.replace(/__PARENT_INDEX__/g, taskIndex + 1); // 1-based for display
        template = template.replace(/__SUBTASK_INDEX__/g, subtaskIndex + 1); // 1-based for display

        // For form field names: Symfony generates them with numeric prototype indices
        // We need to replace [subTaskTemplates][X] with [subTaskTemplates][subtaskIndex]
        // where X is any digit that Symfony generated
        template = template.replace(/(\[subTaskTemplates\]\[)\d+(\])/g, `$1${subtaskIndex}$2`);

        // Also replace in id attributes: plan_taskTemplates_X_subTaskTemplates_Y
        template = template.replace(/(subTaskTemplates_)\d+/g, `$1${subtaskIndex}`);

        // Create temporary element
        const temp = document.createElement('div');
        temp.innerHTML = template;
        const subtaskElement = temp.firstElementChild;

        // Set subtask index as data attribute
        subtaskElement.dataset.subtaskIndex = subtaskIndex;

        // Append before the "add subtask" button container
        const addButtonContainer = subtaskContainer.querySelector('[data-subtask-add-button]');
        if (addButtonContainer) {
            subtaskContainer.insertBefore(subtaskElement, addButtonContainer);
        } else {
            subtaskContainer.appendChild(subtaskElement);
        }

        // Increment subtask count for this task
        this.subtaskCounts.set(taskIndex, subtaskIndex + 1);

        // Hide empty placeholder if it exists
        const placeholder = subtaskContainer.querySelector('[data-subtask-placeholder]');
        if (placeholder) {
            placeholder.style.display = 'none';
        }
    }

    addSubTask(event) {
        event.preventDefault();

        const button = event.currentTarget;
        const taskCard = button.closest('[data-task-index]');
        const taskIndex = parseInt(taskCard.dataset.taskIndex);

        // Use the shared method to add a subtask
        this.addSubTaskToTask(taskIndex);
    }

    removeTask(event) {
        event.preventDefault();
        
        const taskCard = event.currentTarget.closest('[data-task-index]');
        
        if (taskCard) {
            const taskIndex = parseInt(taskCard.dataset.taskIndex);
            taskCard.remove();
            this.subtaskCounts.delete(taskIndex);
            this.updatePlaceholder();
        }
    }

    removeSubTask(event) {
        event.preventDefault();
        
        const subtaskElement = event.currentTarget.closest('[data-subtask-index]');
        const taskCard = event.currentTarget.closest('[data-task-index]');
        
        if (subtaskElement) {
            subtaskElement.remove();
            
            // Check if there are any subtasks left in this task
            if (taskCard) {
                const subtaskContainer = taskCard.querySelector('[data-subtask-container]');
                const remainingSubtasks = subtaskContainer.querySelectorAll('[data-subtask-index]');
                
                // Show placeholder if no subtasks left
                if (remainingSubtasks.length === 0) {
                    const placeholder = subtaskContainer.querySelector('[data-subtask-placeholder]');
                    if (placeholder) {
                        placeholder.style.display = 'block';
                    }
                }
            }
        }
    }

    updatePlaceholder() {
        const placeholder = this.tasksContainerTarget.querySelector('[data-task-placeholder]');
        const tasks = this.tasksContainerTarget.querySelectorAll('[data-task-index]');

        if (placeholder) {
            placeholder.style.display = tasks.length === 0 ? 'block' : 'none';
        }
    }
}

