PRAGMA foreign_keys = ON;

-- 1. Пять новых пользователей (я и четыре соседа по списку группы)
INSERT INTO users (first_name, last_name, email, gender, register_date, occupation_id)
VALUES ('Марина', 'Лепкина', 'marina.lepkina@example.com', 'female', date('now'),
        (SELECT id FROM occupations WHERE name = 'student'));
INSERT INTO users (first_name, last_name, email, gender, register_date, occupation_id)
VALUES ('Артем', 'Кудряшов', 'artem.kudryashov@example.com', 'male', date('now'),
        (SELECT id FROM occupations WHERE name = 'student'));
INSERT INTO users (first_name, last_name, email, gender, register_date, occupation_id)
VALUES ('Даниил', 'Левицкий', 'daniil.levitsky@example.com', 'male', date('now'),
        (SELECT id FROM occupations WHERE name = 'student'));
INSERT INTO users (first_name, last_name, email, gender, register_date, occupation_id)
VALUES ('Богдан', 'Малыхин', 'bogdan.malykhin@example.com', 'male', date('now'),
        (SELECT id FROM occupations WHERE name = 'student'));
INSERT INTO users (first_name, last_name, email, gender, register_date, occupation_id)
VALUES ('Илья', 'Маруси', 'ilya.marusi@example.com', 'male', date('now'),
        (SELECT id FROM occupations WHERE name = 'student'));

-- 2. Три новых фильма
INSERT INTO movies (title, year) VALUES ('Dune: Part Two', 2024);
INSERT INTO movies (title, year) VALUES ('Oppenheimer', 2023);
INSERT INTO movies (title, year) VALUES ('Inside Out 2', 2024);

-- Жанры новых фильмов
INSERT INTO movie_genres (movie_id, genre_id)
SELECT m.id, g.id FROM movies m, genres g
WHERE m.title = 'Dune: Part Two' AND m.year = 2024 AND g.name IN ('Sci-Fi', 'Adventure');
INSERT INTO movie_genres (movie_id, genre_id)
SELECT m.id, g.id FROM movies m, genres g
WHERE m.title = 'Oppenheimer' AND m.year = 2023 AND g.name = 'Drama';
INSERT INTO movie_genres (movie_id, genre_id)
SELECT m.id, g.id FROM movies m, genres g
WHERE m.title = 'Inside Out 2' AND m.year = 2024 AND g.name IN ('Animation', 'Children', 'Comedy');

-- 3. Три новых отзыва от меня
INSERT INTO ratings (user_id, movie_id, rating, timestamp)
VALUES ((SELECT id FROM users WHERE email = 'marina.lepkina@example.com'),
        (SELECT id FROM movies WHERE title = 'Dune: Part Two' AND year = 2024),
        4.5, CAST(strftime('%s', 'now') AS INTEGER));
INSERT INTO ratings (user_id, movie_id, rating, timestamp)
VALUES ((SELECT id FROM users WHERE email = 'marina.lepkina@example.com'),
        (SELECT id FROM movies WHERE title = 'Oppenheimer' AND year = 2023),
        5.0, CAST(strftime('%s', 'now') AS INTEGER));
INSERT INTO ratings (user_id, movie_id, rating, timestamp)
VALUES ((SELECT id FROM users WHERE email = 'marina.lepkina@example.com'),
        (SELECT id FROM movies WHERE title = 'Inside Out 2' AND year = 2024),
        4.0, CAST(strftime('%s', 'now') AS INTEGER));