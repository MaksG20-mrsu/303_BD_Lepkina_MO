import os
import csv

OUTPUT_SQL = "db_init.sql"

def escape_sql(value):
    return value.replace("'", "''")

def generate_sql():
    sql_lines = []
    
    # Сброс старых таблиц
    tables = ['movies', 'ratings', 'tags', 'users']
    for table in tables:
        sql_lines.append(f"DROP TABLE IF EXISTS {table};")
    sql_lines.append("")

    # 1. Схема movies
    sql_lines.append("""CREATE TABLE movies (
    id INTEGER PRIMARY KEY,
    title TEXT,
    year INTEGER,
    genres TEXT
);""")

    # 2. Схема ratings (id убран из списка вставляемых полей, так как в CSV всего 4 колонки)
    sql_lines.append("""CREATE TABLE ratings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    movie_id INTEGER,
    rating REAL,
    timestamp INTEGER
);""")

    # 3. Схема tags (id убран из списка вставляемых полей, так как в CSV всего 4 колонки)
    sql_lines.append("""CREATE TABLE tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    movie_id INTEGER,
    tag TEXT,
    timestamp INTEGER
);""")

    # 4. Схема users
    sql_lines.append("""CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    name TEXT,
    email TEXT,
    gender TEXT,
    register_date TEXT,
    occupation TEXT
);""")
    sql_lines.append("\n" + "-"*40 + "\n")

    def file_to_inserts(filename, table_name, columns, delimiter=','):
        if not os.path.exists(filename):
            print(f"Ошибка: Файл {filename} не найден.")
            return
        
        with open(filename, mode='r', encoding='utf-8') as f:
            # Автоматически определяем разделитель для текстовых файлов, если передан дефолтный
            content_preview = f.read(2048)
            f.seek(0)
            
            current_delimiter = delimiter
            if filename.endswith('.txt'):
                if '\t' in content_preview:
                    current_delimiter = '\t'
                elif ';' in content_preview:
                    current_delimiter = ';'
                elif ',' in content_preview:
                    current_delimiter = ','

            reader = csv.reader(f, delimiter=current_delimiter)
            try:
                next(reader)  # Пропуск заголовка
            except StopIteration:
                return
            
            for row in reader:
                if not row or len(row) == 0:
                    continue
                
                # Защита от пустых или сломанных строк
                if len(row) != len(columns):
                    continue
                
                escaped_row = []
                for x in row:
                    x = x.strip()
                    if x.isdigit():
                        escaped_row.append(x)
                    else:
                        try:
                            float(x)
                            escaped_row.append(x)
                        except ValueError:
                            escaped_row.append(f"'{escape_sql(x)}'")
                
                col_str = ", ".join(columns)
                val_str = ", ".join(escaped_row)
                sql_lines.append(f"INSERT INTO {table_name} ({col_str}) VALUES ({val_str});")

    # Передаем только те колонки, которые ФИЗИЧЕСКИ есть в файлах данных
    file_to_inserts("movies.csv", "movies", ["id", "title", "year", "genres"], delimiter=',')
    file_to_inserts("ratings.csv", "ratings", ["user_id", "movie_id", "rating", "timestamp"], delimiter=',')
    file_to_inserts("tags.csv", "tags", ["user_id", "movie_id", "tag", "timestamp"], delimiter=',')
    file_to_inserts("users.txt", "users", ["id", "name", "email", "gender", "register_date", "occupation"])

    with open(OUTPUT_SQL, "w", encoding="utf-8") as f:
        f.write("\n".join(sql_lines))
    print(f"Скрипт {OUTPUT_SQL} успешно сгенерирован!")

if __name__ == "__main__":
    generate_sql()
