-- Create database
CREATE DATABASE IF NOT EXISTS usdt_investment;
USE usdt_investment;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    wallet_address VARCHAR(100) UNIQUE NOT NULL,
    profit_balance DECIMAL(15,2) DEFAULT 0,
    bonus_balance DECIMAL(15,2) DEFAULT 0,
    referral_code VARCHAR(20) UNIQUE NOT NULL,
    referred_by INT,
    referral_level INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Investments table
CREATE TABLE investments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    daily_profit DECIMAL(5,4) NOT NULL,
    total_profit DECIMAL(15,2) DEFAULT 0,
    contract_duration INT DEFAULT 100,
    start_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    end_date DATETIME NOT NULL,
    status ENUM('pending', 'active', 'completed', 'terminated') DEFAULT 'pending',
    last_profit_update DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Transactions table
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('deposit', 'withdrawal', 'profit', 'referral_bonus') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'USDT',
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    wallet_address VARCHAR(100) NOT NULL,
    tx_hash VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Create indexes for better performance
CREATE INDEX idx_user_wallet ON users(wallet_address);
CREATE INDEX idx_user_referral ON users(referral_code);
CREATE INDEX idx_investment_status ON investments(status);
CREATE INDEX idx_transaction_status ON transactions(status);
CREATE INDEX idx_transaction_type ON transactions(type);