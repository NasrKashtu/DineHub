-- ============================================
-- CIT6224 Lab Assignment - Restaurant Listing and Review System
-- Database: fooddb
-- ============================================

CREATE DATABASE IF NOT EXISTS fooddb;
USE fooddb;

-- ============================================
-- Table: restaurants
-- ============================================
CREATE TABLE IF NOT EXISTS restaurants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    cuisine_type VARCHAR(50) NOT NULL,
    location VARCHAR(150) NOT NULL,
    description TEXT,
    opening_hours VARCHAR(100),
    image VARCHAR(255) DEFAULT 'default.jpg',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: reviews
-- Linked to restaurants via restaurant_id (foreign key)
-- ============================================
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Sample Data: restaurants
-- ============================================
INSERT INTO restaurants (name, cuisine_type, location, description, opening_hours, image) VALUES
('Nasi Lemak Corner', 'Malaysian', 'Puchong, Selangor', 'Authentic local nasi lemak with sambal made fresh daily.', '7:00 AM - 3:00 PM', 'default.jpg'),
('Sakura Sushi House', 'Japanese', 'Bandar Sunway, Selangor', 'Fresh sushi and sashimi platters in a cozy setting.', '11:00 AM - 10:00 PM', 'default.jpg'),
('Trattoria Bella', 'Italian', 'Bangsar, Kuala Lumpur', 'Wood-fired pizza and handmade pasta.', '12:00 PM - 11:00 PM', 'default.jpg'),
('Spice Route', 'Indian', 'Brickfields, Kuala Lumpur', 'Traditional banana leaf rice and curries.', '10:00 AM - 9:30 PM', 'default.jpg'),
('Golden Wok', 'Chinese', 'Petaling Jaya, Selangor', 'Dim sum and Cantonese specialties.', '8:00 AM - 9:00 PM', 'default.jpg');

-- ============================================
-- Sample Data: reviews
-- ============================================
INSERT INTO reviews (restaurant_id, customer_name, email, rating, review_text) VALUES
(1, 'Ahmad Faiz', 'ahmad.faiz@example.com', 5, 'Best nasi lemak in town, sambal is perfect!'),
(2, 'Mei Ling', 'meiling@example.com', 4, 'Fresh sushi, slightly pricey but worth it.'),
(3, 'John Tan', 'john.tan@example.com', 5, 'Authentic Italian taste, great service.');
