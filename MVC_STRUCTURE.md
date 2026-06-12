# CityCare MVC Structure

This document describes the Model-View-Controller (MVC) architecture for the CityCare project.

## Directory Structure

```
CityCare/
├── app/
│   ├── Controllers/           # Application controllers
│   │   ├── AuthController.php
│   │   ├── CitizenController.php
│   │   ├── AgentController.php
│   │   ├── AdminController.php
│   │   ├── ServiceController.php
│   │   ├── ComplaintController.php
│   │   └── MessageController.php
│   │
│   ├── Models/                # Data models
│   │   ├── UserModel.php
│   │   ├── ServiceModel.php
│   │   ├── ComplaintModel.php
│   │   ├── ServiceRequestModel.php
│   │   ├── CommentModel.php
│   │   └── MessageModel.php
│   │
│   ├── Views/                 # View templates
│   │   ├── layouts/
│   │   │   ├── header.php
│   │   │   └── footer.php
│   │   ├── auth/
│   │   │   ├── login.php
│   │   │   └── register.php
│   │   ├── citizen/
│   │   │   └── dashboard.php
│   │   ├── admin/
│   │   │   └── dashboard.php
│   │   ├── services/
│   │   │   └── list.php
│   │   └── complaints/
│   │       └── list.php
│   │
│   └── autoload.php           # Autoloader for classes
│
├── public/
│   ├── index.php              # Main entry point
│   ├── css/                   # Stylesheets
│   ├── js/                    # JavaScript files
│   └── images/                # Image assets
│
├── config/
│   └── database.php           # Database configuration
│
├── database/
│   └── ma_base.sql            # Database schema
│
└── README.md                  # Project documentation
```

## Architecture Overview

### Models (app/Models/)
Responsible for data handling and database operations:
- **UserModel.php**: User management
- **ServiceModel.php**: Service operations
- **ComplaintModel.php**: Complaint handling
- **ServiceRequestModel.php**: Service request operations
- **CommentModel.php**: Comment management
- **MessageModel.php**: Messaging operations

### Views (app/Views/)
HTML templates for user interface:
- **layouts/**: Reusable layout templates (header, footer)
- **auth/**: Authentication pages (login, register)
- **citizen/**: Citizen interface pages
- **admin/**: Administrator pages
- **services/**: Service display pages
- **complaints/**: Complaint pages

### Controllers (app/Controllers/)
Handle business logic and request processing:
- **AuthController.php**: Authentication logic
- **CitizenController.php**: Citizen operations
- **AgentController.php**: Agent operations
- **AdminController.php**: Administrative operations
- **ServiceController.php**: Service management
- **ComplaintController.php**: Complaint management
- **MessageController.php**: Messaging system

## Data Flow

1. **Request** → User submits a request via the browser
2. **Routing** → public/index.php routes the request to appropriate controller
3. **Controller** → Processes the request and interacts with models
4. **Model** → Retrieves/updates data from the database
5. **View** → Controller loads the view with data
6. **Response** → HTML is sent back to the user

## Benefits of MVC Structure

✅ **Separation of Concerns** - Each component has a single responsibility
✅ **Code Reusability** - Models can be used by multiple controllers
✅ **Easier Testing** - Components can be tested independently
✅ **Better Maintainability** - Organized code is easier to maintain
✅ **Scalability** - Easy to add new features and components
✅ **Collaboration** - Multiple developers can work on different components

## Adding New Features

### To add a new feature:

1. **Create a Model** (app/Models/FeatureModel.php)
   - Define database operations

2. **Create a Controller** (app/Controllers/FeatureController.php)
   - Define business logic
   - Interact with models

3. **Create Views** (app/Views/feature/)
   - Define user interface
   - Load data from controller

4. **Add Routes**
   - Update routing logic in public/index.php

## Configuration

- **Database**: config/database.php
- **Base URL**: Configured in public/index.php
- **Database Schema**: database/ma_base.sql

## Next Steps

1. Migrate existing code to appropriate MVC locations
2. Update file includes to use new structure
3. Test all functionality
4. Update documentation with new file locations
