<!-- Create a new file for common styles -->
<style>
    /* Header container styling */
    .header {
        background-color: #2c3e50;    /* Dark blue background */
        color: white;                  /* White text */
        padding: 1rem;                 /* Internal spacing */
        text-align: center;            /* Center align content */
        position: relative;            /* For absolute positioning of children */
        margin-bottom: 2rem;           /* Space below header */
    }

    /* Back button styling */
    .back-btn {
        position: absolute;            /* Position relative to header */
        left: 20px;                   /* Distance from left edge */
        top: 20px;                    /* Distance from top edge */
        background: none;             /* Transparent background */
        border: none;                 /* Remove border */
        color: white;                 /* White text */
        font-size: 18px;              /* Text size */
        cursor: pointer;              /* Hand cursor on hover */
    }

    /* Menu button styling */
    .menu-btn {
        position: absolute;            /* Position relative to header */
        right: 20px;                  /* Distance from right edge */
        top: 20px;                    /* Distance from top edge */
        background: none;             /* Transparent background */
        border: none;                 /* Remove border */
        color: white;                 /* White text */
        font-size: 24px;              /* Text size */
        cursor: pointer;              /* Hand cursor on hover */
    }

    /* Sidebar navigation styling */
    .sidebar {
        position: fixed;              /* Fixed position on screen */
        right: -250px;               /* Hide sidebar off-screen */
        top: 0;                      /* Align to top */
        width: 250px;                /* Sidebar width */
        height: 100%;                /* Full height */
        background-color: #2c3e50;    /* Dark blue background */
        transition: right 0.3s;       /* Smooth sliding animation */
        padding-top: 60px;           /* Space at top */
        z-index: 1000;               /* Ensure sidebar appears above other content */
    }

    /* Active state for sidebar */
    .sidebar.active {
        right: 0;                    /* Show sidebar */
    }

    /* Sidebar link styling */
    .sidebar a {
        display: block;              /* Make links block-level */
        color: white;                /* White text */
        padding: 15px 25px;          /* Internal spacing */
        text-decoration: none;       /* Remove underline */
    }

    /* Sidebar link hover effect */
    .sidebar a:hover {
        background-color: #34495e;    /* Lighter blue on hover */
    }
</style>