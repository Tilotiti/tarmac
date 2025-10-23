# Fixtures Summary

## Created Users

### 1. Admin User
- **Name:** Thibault HENRY
- **Email:** thibault@henry.pro
- **Password:** admin
- **Role:** ROLE_ADMIN
- **Club Memberships:** Manager and Inspector in "Les planeurs de Bailleau" (cvve)

### 2. Manager Only
- **Name:** Jean DUPONT
- **Email:** manager@cvve.fr
- **Password:** manager123
- **Role:** ROLE_USER
- **Club Memberships:** Manager in "Les planeurs de Bailleau" (cvve)

### 3. Inspector Only
- **Name:** Marie MARTIN
- **Email:** inspector@cvve.fr
- **Password:** inspector123
- **Role:** ROLE_USER
- **Club Memberships:** Inspector in "Les planeurs de Bailleau" (cvve)

### 4. Manager and Inspector
- **Name:** Pierre DURAND
- **Email:** manager.inspector@cvve.fr
- **Password:** managerinspector123
- **Role:** ROLE_USER
- **Club Memberships:** Manager and Inspector in "Les planeurs de Bailleau" (cvve)

### 5. Member (No special roles)
- **Name:** Sophie BERNARD
- **Email:** member@cvve.fr
- **Password:** member123
- **Role:** ROLE_USER
- **Club Memberships:** Member in "Les planeurs de Bailleau" (cvve)

### 6. User Without Club
- **Name:** Lucas PETIT
- **Email:** no.club@example.com
- **Password:** noclub123
- **Role:** ROLE_USER
- **Club Memberships:** None

## Created Club

### Les planeurs de Bailleau
- **Name:** Les planeurs de Bailleau
- **Subdomain:** cvve
- **Description:** Club de vol à voile situé à Bailleau
- **Status:** Active

## How to Use

### Load Fixtures
```bash
php bin/console doctrine:fixtures:load
```

### Reload Fixtures (purge and reload)
```bash
php bin/console doctrine:fixtures:load --purge-with-truncate
```

### Access the Application

1. **Admin Access:**
   - URL: http://tarmac.wip
   - Email: thibault@henry.pro
   - Password: admin

2. **Club Access (CVVE):**
   - URL: http://cvve.tarmac.wip
   - Any of the CVVE users can login

3. **Test Different Roles:**
   - Manager: manager@cvve.fr / manager123
   - Inspector: inspector@cvve.fr / inspector123
   - Manager+Inspector: manager.inspector@cvve.fr / managerinspector123
   - Member: member@cvve.fr / member123

4. **User Without Club:**
   - Email: no.club@example.com
   - Password: noclub123
   - This user has no club memberships

## Notes

- All users are verified and active
- All passwords are hashed using Symfony's UserPasswordHasherInterface
- The fixtures file is located at: `src/DataFixtures/AppFixtures.php`
- You can modify the fixtures file to add more test data as needed

