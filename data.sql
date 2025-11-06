CREATE TABLE review_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id VARCHAR(50) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    sent_date DATETIME,
    status ENUM('sent', 'opened', 'completed'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE customer_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    rating INT,
    comments TEXT,
    submission_date DATETIME,
    status ENUM('pending', 'approved', 'rejected')
);