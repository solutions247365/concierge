(function () {
    'use strict';

    var search = document.getElementById('task-search');
    var list = document.getElementById('task-list');
    var searchEmpty = document.getElementById('search-empty');

    if (search && list) {
        search.addEventListener('input', function () {
            var term = search.value.trim().toLowerCase();
            var items = list.querySelectorAll('li');
            var visibleCount = 0;
            items.forEach(function (item) {
                var matches = item.textContent.toLowerCase().indexOf(term) !== -1;
                item.hidden = !matches;
                if (matches) {
                    visibleCount++;
                }
            });
            if (searchEmpty) {
                searchEmpty.hidden = visibleCount !== 0;
            }
        });
    }

    var prevBtn = document.getElementById('cal-prev');
    var nextBtn = document.getElementById('cal-next');
    var weekdayEl = document.getElementById('cal-weekday');
    var dateEl = document.getElementById('cal-date');
    var tasksEl = document.getElementById('cal-tasks');

    if (prevBtn && nextBtn && weekdayEl && dateEl && tasksEl) {
        var tasks = window.CAL_TASKS || [];
        var MIN_OFFSET = -7;
        var MAX_OFFSET = 7;
        var offset = 0;

        function isoDate(date) {
            var y = date.getFullYear();
            var m = String(date.getMonth() + 1).padStart(2, '0');
            var d = String(date.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + d;
        }

        function render() {
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            var day = new Date(today);
            day.setDate(day.getDate() + offset);

            weekdayEl.textContent = day.toLocaleDateString(undefined, { weekday: 'long' });
            dateEl.textContent = day.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });

            var iso = isoDate(day);
            var dayTasks = tasks.filter(function (t) {
                return t.due_date === iso;
            });

            tasksEl.innerHTML = '';
            if (dayTasks.length === 0) {
                var empty = document.createElement('li');
                empty.className = 'cal-empty';
                empty.textContent = 'No tasks due';
                tasksEl.appendChild(empty);
            } else {
                dayTasks.forEach(function (t) {
                    var li = document.createElement('li');
                    li.className = 'class-' + t.class;
                    var span = document.createElement('span');
                    span.textContent = t.text;
                    li.appendChild(span);
                    tasksEl.appendChild(li);
                });
            }

            prevBtn.disabled = offset <= MIN_OFFSET;
            nextBtn.disabled = offset >= MAX_OFFSET;
        }

        prevBtn.addEventListener('click', function () {
            if (offset > MIN_OFFSET) {
                offset--;
                render();
            }
        });

        nextBtn.addEventListener('click', function () {
            if (offset < MAX_OFFSET) {
                offset++;
                render();
            }
        });

        render();
    }
})();
