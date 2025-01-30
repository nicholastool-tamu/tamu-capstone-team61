
<style>
    .header {
        background-color: #2c3e50;    
        color: white;                  
        padding: 1rem;                 
        text-align: center;            
        position: relative;            
        margin-bottom: 2rem;           
    }

    .back-btn {
        position: absolute;            
        left: 20px;                  
        top: 20px;                    
        background: none;             
        border: none;                 
        color: white;                 
        font-size: 18px;              
        cursor: pointer;              
    }

   
    .menu-btn {
        position: absolute;            
        right: 20px;                  
        top: 20px;                   
        background: none;            
        border: none;                
        color: white;                 
        font-size: 24px;             
        cursor: pointer;              
    }

    
    .sidebar {
        position: fixed;             
        right: -250px;              
        top: 0;                      
        width: 250px;              
        height: 100%;               
        background-color: #2c3e50;    
        transition: right 0.3s;       
        padding-top: 60px;           
        z-index: 1000;               
    }

    
    .sidebar.active {
        right: 0;                    
    }

  
    .sidebar a {
        display: block;              
        color: white;                
        padding: 15px 25px;          
        text-decoration: none;       
    }


    .sidebar a:hover {
        background-color: #34495e;   
    }
</style>