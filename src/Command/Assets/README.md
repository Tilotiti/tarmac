# Maintenance Plans Import

This directory contains assets for importing maintenance plans into the Tarmac application.

## CSV Template

The `maintenance_plans_template.csv` file serves as a template for importing maintenance plans with their tasks and subtasks.

### CSV Structure

Each row in the CSV represents a **subtask**. Plans and tasks are automatically grouped based on their names.

| Column | Description | Required | Valid Values |
|--------|-------------|----------|--------------|
| `plan_name` | Name of the maintenance plan | Yes | Any string (max 180 chars) |
| `plan_description` | Description of the plan | No | Any text |
| `plan_equipment_type` | Type of equipment | Yes | `glider`, `airplane`, or `facility` |
| `task_title` | Title of the task | Yes | Any string (max 180 chars) |
| `task_description` | Description of the task | No | Any text |
| `task_position` | Position/order of the task within the plan | Yes | Integer (1, 2, 3, ...) |
| `subtask_title` | Title of the subtask | Yes | Any string (max 180 chars) |
| `subtask_description` | Description of the subtask | No | Any text |
| `subtask_difficulty` | Difficulty level of the subtask | Yes | Integer between 1 (Débutant) and 3 (Expert) |
| `subtask_requires_inspection` | Whether the subtask requires inspection | Yes | `0` or `1` (or `true`/`false`) |
| `subtask_position` | Position/order of the subtask within the task | Yes | Integer (1, 2, 3, ...) |

### Example CSV Format

```csv
plan_name,plan_description,plan_equipment_type,task_title,task_description,task_position,subtask_title,subtask_description,subtask_difficulty,subtask_requires_inspection,subtask_position
"Visite 100h","Inspection complète du planeur toutes les 100h de vol","glider","Inspection fuselage","Vérification complète du fuselage",1,"Vérifier l'état de la peinture","Inspecter visuellement l'état de la peinture, noter tout dommage",1,0,1
"Visite 100h","Inspection complète du planeur toutes les 100h de vol","glider","Inspection fuselage","Vérification complète du fuselage",1,"Contrôler les fixations","Vérifier le serrage de toutes les fixations structurelles",3,1,2
```

## Import Command

### Basic Usage

```bash
# Import plans from a CSV file
symfony console app:import:maintenance-plans /path/to/your/plans.csv
```

### Options

#### `--club-subdomain` (or `-c`)
Assign the imported plans to a specific club:

```bash
symfony console app:import:maintenance-plans plans.csv --club-subdomain=demo
```

Without this option, plans will be created without club assignment (you'll need to assign them manually later).

#### `--dry-run`
Test the import without persisting data to the database:

```bash
symfony console app:import:maintenance-plans plans.csv --dry-run
```

This is useful to validate your CSV file before actually importing the data.

### Complete Example

```bash
# Import the template file for the demo club
symfony console app:import:maintenance-plans \
  src/Command/Assets/maintenance_plans_template.csv \
  --club-subdomain=demo
```

### Output

The command will display:
- Each plan being created
- Each task being created (with tree structure)
- Each subtask being created (with difficulty and inspection badge)
- A summary table showing counts of plans, tasks, and subtasks
- Success or error messages

### Tips

1. **Use the template**: Start with `maintenance_plans_template.csv` and modify it according to your needs
2. **Test first**: Always run with `--dry-run` first to validate your CSV
3. **Check equipment types**: Make sure to use valid equipment types: `glider`, `airplane`, or `facility`
4. **Validate difficulty**: Difficulty must be between 1 and 3 (1=Débutant, 2=Expérimenté, 3=Expert)
5. **Group properly**: Rows with the same `plan_name` and `plan_equipment_type` will be grouped into the same plan
6. **Order matters**: Use `task_position` and `subtask_position` to control the display order

### Error Handling

The command will:
- Validate the CSV header structure
- Check that the specified club exists (if `--club-subdomain` is provided)
- Validate equipment types, difficulty levels, and inspection flags
- Report errors with row numbers if data is invalid
- Stop processing and show errors if validation fails

If errors occur, fix the CSV file and run the command again.

