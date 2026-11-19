PRAGMA foreign_keys = ON;

DROP TABLE IF EXISTS works;
DROP TABLE IF EXISTS appointment_services;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS schedules;
DROP TABLE IF EXISTS cars;
DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS masters;

-- Мастера. Уволенные не удаляются: у них заполнена dismissal_date
CREATE TABLE masters (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    last_name       TEXT NOT NULL,
    first_name      TEXT NOT NULL,
    phone           TEXT,
    hire_date       TEXT NOT NULL DEFAULT (date('now')),
    dismissal_date  TEXT,
    percent         REAL NOT NULL DEFAULT 30
                    CHECK (percent >= 0 AND percent <= 100),
    CHECK (dismissal_date IS NULL OR dismissal_date >= hire_date)
);

-- Индивидуальный график мастера: рабочие часы по дням недели
CREATE TABLE schedules (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    master_id   INTEGER NOT NULL,
    weekday     INTEGER NOT NULL CHECK (weekday BETWEEN 1 AND 7),  -- 1 = понедельник
    start_time  TEXT    NOT NULL,   -- 'HH:MM'
    end_time    TEXT    NOT NULL,
    CHECK (end_time > start_time),
    UNIQUE (master_id, weekday),
    FOREIGN KEY (master_id) REFERENCES masters(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- Справочник услуг
CREATE TABLE services (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT    NOT NULL UNIQUE,
    duration_min  INTEGER NOT NULL CHECK (duration_min > 0),
    price         REAL    NOT NULL CHECK (price >= 0)
);

CREATE TABLE clients (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name  TEXT NOT NULL,
    phone      TEXT NOT NULL UNIQUE
);

CREATE TABLE cars (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id  INTEGER NOT NULL,
    plate      TEXT    NOT NULL UNIQUE,
    model      TEXT    NOT NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- Предварительная запись к мастеру
CREATE TABLE appointments (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    master_id   INTEGER NOT NULL,
    car_id      INTEGER NOT NULL,
    start_time  TEXT    NOT NULL,   -- 'YYYY-MM-DD HH:MM'
    end_time    TEXT    NOT NULL,   -- считается по длительности выбранных услуг
    status      TEXT    NOT NULL DEFAULT 'planned'
                CHECK (status IN ('planned', 'done', 'cancelled')),
    CHECK (end_time > start_time),
    FOREIGN KEY (master_id) REFERENCES masters(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (car_id) REFERENCES cars(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

-- Запрет пересекающихся записей к одному мастеру (отменённые не учитываются)
CREATE TRIGGER trg_appointments_no_overlap_insert
BEFORE INSERT ON appointments
WHEN NEW.status <> 'cancelled'
BEGIN
    SELECT RAISE(ABORT, 'Мастер занят в это время')
    WHERE EXISTS (
        SELECT 1 FROM appointments a
        WHERE a.master_id = NEW.master_id
          AND a.status <> 'cancelled'
          AND a.start_time < NEW.end_time
          AND a.end_time   > NEW.start_time
    );
END;

CREATE TRIGGER trg_appointments_no_overlap_update
BEFORE UPDATE ON appointments
WHEN NEW.status <> 'cancelled'
BEGIN
    SELECT RAISE(ABORT, 'Мастер занят в это время')
    WHERE EXISTS (
        SELECT 1 FROM appointments a
        WHERE a.master_id = NEW.master_id
          AND a.id <> NEW.id
          AND a.status <> 'cancelled'
          AND a.start_time < NEW.end_time
          AND a.end_time   > NEW.start_time
    );
END;

-- Нельзя записать к мастеру после даты его увольнения
CREATE TRIGGER trg_appointments_master_not_dismissed
BEFORE INSERT ON appointments
WHEN (SELECT dismissal_date FROM masters WHERE id = NEW.master_id) IS NOT NULL
 AND date(NEW.start_time) > (SELECT dismissal_date FROM masters WHERE id = NEW.master_id)
BEGIN
    SELECT RAISE(ABORT, 'Мастер уже уволен');
END;

-- Выбранные услуги в записи (многие ко многим)
CREATE TABLE appointment_services (
    appointment_id  INTEGER NOT NULL,
    service_id      INTEGER NOT NULL,
    PRIMARY KEY (appointment_id, service_id),
    FOREIGN KEY (appointment_id) REFERENCES appointments(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

-- Фактически выполненные работы
CREATE TABLE works (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    master_id       INTEGER NOT NULL,
    car_id          INTEGER NOT NULL,
    service_id      INTEGER NOT NULL,
    appointment_id  INTEGER,
    work_date       TEXT    NOT NULL DEFAULT (date('now')),
    price           REAL    NOT NULL CHECK (price >= 0),  -- цена на момент выполнения
    FOREIGN KEY (master_id) REFERENCES masters(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (car_id) REFERENCES cars(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id)
        ON DELETE SET NULL ON UPDATE CASCADE
);

-- ===== Тестовые данные =====
INSERT INTO masters (last_name, first_name, phone, hire_date, dismissal_date, percent) VALUES
    ('Иванов',   'Пётр', '+79001112233', '2024-01-10', NULL,         35),
    ('Сидоров',  'Олег', '+79002223344', '2023-05-01', NULL,         30),
    ('Кузнецов', 'Иван', '+79003334455', '2022-03-15', '2026-06-30', 25);

INSERT INTO schedules (master_id, weekday, start_time, end_time) VALUES
    (1, 1, '09:00', '18:00'), (1, 2, '09:00', '18:00'), (1, 3, '09:00', '18:00'),
    (2, 3, '12:00', '20:00'), (2, 4, '12:00', '20:00'), (2, 5, '12:00', '20:00'),
    (3, 1, '10:00', '19:00');

INSERT INTO services (name, duration_min, price) VALUES
    ('Замена масла',             30, 1500),
    ('Диагностика подвески',     60, 2000),
    ('Замена тормозных колодок', 90, 3500),
    ('Шиномонтаж',               45, 2400);

INSERT INTO clients (full_name, phone) VALUES
    ('Смирнов Алексей', '+79101112233'),
    ('Орлова Мария',    '+79102223344');

INSERT INTO cars (client_id, plate, model) VALUES
    (1, 'А123ВС77', 'Toyota Camry'),
    (2, 'К555ОР99', 'Kia Rio');

INSERT INTO appointments (master_id, car_id, start_time, end_time, status) VALUES
    (1, 1, '2026-11-20 10:00', '2026-11-20 11:30', 'planned'),
    (2, 2, '2026-11-18 14:00', '2026-11-18 15:30', 'done');

INSERT INTO appointment_services (appointment_id, service_id) VALUES
    (1, 1), (1, 2), (2, 3);

INSERT INTO works (master_id, car_id, service_id, appointment_id, work_date, price) VALUES
    (2, 2, 3, 2,    '2026-11-18', 3500),
    (1, 1, 1, NULL, '2026-11-10', 1500),
    (3, 2, 4, NULL, '2026-05-12', 2400);