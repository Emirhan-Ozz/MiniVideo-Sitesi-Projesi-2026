USE minivideo_db;

INSERT INTO Users (Username, Email, Password, Role) VALUES
('admin',  'admin@mini.com',  '$2y$12$eevT/M0JVanAApZN6mWuOOXoZWzUDZrZGbRirLbSejd5eeZ.u.xvm', 'admin'),
('ahmet',  'ahmet@mini.com',  '$2y$12$PSDtq.zqHpNHsrGRrw3QQu0tfKinRrfyIHM.NQqRb6WBeuW42p/2.', 'user'),
('zeynep', 'zeynep@mini.com', '$2y$12$ShNybX93N.TsEig35xh/keMC0vEa6mVSiJNAYfNvrjl/dUd1DuqIi', 'user'),
('mehmet', 'mehmet@mini.com', '$2y$12$JlJ.QmLscYK7QjXZNzeVnuQPknSkX3GK4S.5ieX8Jsk67wKybzF7C', 'user')
ON DUPLICATE KEY UPDATE
Password = VALUES(Password),
Role = VALUES(Role);

INSERT INTO Categories (CategoryName) VALUES
('Eğitim'), ('Teknoloji'), ('Oyun'), ('Müzik'), ('Spor')
ON DUPLICATE KEY UPDATE CategoryName = VALUES(CategoryName);

INSERT INTO Videos (Title, Description, VideoURL, ThumbnailURL, CategoryID, UploaderID) VALUES
('PHP Dersleri', 'Temel PHP anlatımı', 'https://www.youtube.com/embed/OK_JCtrrv-c', 'https://img.youtube.com/vi/OK_JCtrrv-c/hqdefault.jpg', 1, 1),
('HTML CSS Giriş', 'Web tasarıma başlangıç', 'https://www.youtube.com/embed/qz0aGYrrlhU', 'https://img.youtube.com/vi/qz0aGYrrlhU/hqdefault.jpg', 1, 1)
ON DUPLICATE KEY UPDATE Title = VALUES(Title);
