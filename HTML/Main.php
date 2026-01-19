<?php
session_start();
include_once '../JS/headerFooter.php';
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Main</title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
        <link rel="stylesheet" href="../CSS/Main.css"/>
    </head>
    <body>
        
        <special-header></special-header>
        
        <div class="layout">
            
            <special-aside></special-aside>
            
            <main class="main-content">
                <h2>Discover the Best Fashion Trends</h2>
                
                <div class="category-grid">
                    <div class="category-card">
                        <img src="../images/Opium.jpg" alt="Opium">
                        <div class="text-overlay">Opium</div>
                    </div>
                    <div class="category-card">
                        <img src="../images/Old_money.jpg" alt="Old money">
                        <div class="text-overlay">Old money</div>
                    </div>
                    <div class="category-card">
                        <img src="../images/Streetwear.jpg" alt="Streetwear">
                        <div class="text-overlay">Streetwear</div>
                    </div>
                    <div class="category-card">
                        <img src="../images/Accessories.jpg" alt="Accessories">
                        <div class="text-overlay">Accessories</div>
                    </div>
                </div>
                
                <h3>Browse by Category</h3>
                
                <div class="category-grid1">
                    <div class="category-card">
                        <img src="../images/Hats.jpg" alt="Hats">
                        <div class="text-overlay">Hats</div>
                    </div>
                    <div class="category-card">
                        <img src="../images/Jackets.jpg" alt="Jackets">
                        <div class="text-overlay">Jackets</div>
                    </div>
                    <div class="category-card">
                        <img src="../images/Jeans.jpg" alt="Denim">
                        <div class="text-overlay">Denim</div>
                    </div>
                    <div class="category-card">
                        <img src="../images/T_shirts.jpg" alt="T-shirts">
                        <div class="text-overlay">T-shirts</div>
                    </div>
                    <div class="category-card">
                        <img src="../images/Sweats.jpg" alt="Sweats">
                        <div class="text-overlay">Sweats</div>
                    </div>
                    <div class="category-card">
                        <img src="../images/Boots.jpg" alt="Boots">
                        <div class="text-overlay">Boots</div>
                    </div>
                    <div class="category-card">
                        <img src="../images/Bags.jpg" alt="Bags">
                        <div class="text-overlay">Bags</div>
                    </div>
                    <div class="category-card">
                        <img src="../images/Coats.jpg" alt="Coats">
                        <div class="text-overlay">Coats</div>
                    </div>
                    <div class="category-card">
                        <img src="../images/Glasses.jpg" alt="Glasses">
                        <div class="text-overlay">Glasses</div>
                    </div>
                    <div class="category-card">
                        <img src="../images/Dresses.jpg" alt="Dresses">
                        <div class="text-overlay">Dresses</div>
                    </div>
                    <div class="category-card">
                        <img src="../images/Skirts.jpg" alt="Skirts">
                        <div class="text-overlay">Skirts</div>
                    </div>
                    <div class="category-card">
                        <img src="../images/Belts.jpg" alt="Belts">
                        <div class="text-overlay">Belts</div>
                    </div>
                    <div class="category-card">
                        <img src="../images/Knitwear.jpg" alt="Knitwear">
                        <div class="text-overlay">Knitwear</div>
                    </div>
                    <div class="category-card">
                        <img src="../images/Polo.jpg" alt="Polo">
                        <div class="text-overlay">Polo</div>
                    </div>
                </div>
            </main>
        </div>
        
        <special-footer></special-footer>
        
    </body>
    </html>

