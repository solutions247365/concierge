(function () {
    'use strict';

    // --- Search ---
    var search = document.getElementById('task-search');
    var searchBtn = document.getElementById('search-btn');
    var list = document.getElementById('task-list');
    var searchEmpty = document.getElementById('search-empty');

    function filterTasks() {
        if (!search || !list) {
            return;
        }
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
    }

    if (search) {
        search.addEventListener('input', filterTasks);
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                filterTasks();
            }
        });
    }

    if (searchBtn) {
        searchBtn.addEventListener('click', filterTasks);
    }

    // --- Confirmation on destructive actions ---
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    var resetForm = document.getElementById('reset-form');
    if (resetForm) {
        resetForm.addEventListener('submit', function (e) {
            if (!window.confirm('Reset your task list? This deletes every task and cannot be undone.')) {
                e.preventDefault();
            }
        });
    }

    // --- Calendar carousel (3 days: previous, current, next) ---
    var prevBtn = document.getElementById('cal-prev');
    var nextBtn = document.getElementById('cal-next');
    var prevCard = document.getElementById('cal-day-prev');
    var currentCard = document.getElementById('cal-day-current');
    var nextCard = document.getElementById('cal-day-next');

    if (prevBtn && nextBtn && prevCard && currentCard && nextCard) {
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

        function fillCard(card, dayOffset) {
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            var day = new Date(today);
            day.setDate(day.getDate() + dayOffset);

            card.querySelector('.cal-weekday').textContent = day.toLocaleDateString(undefined, { weekday: 'long' });
            card.querySelector('.cal-date').textContent = day.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });

            var iso = isoDate(day);
            var dayTasks = tasks.filter(function (t) {
                return t.due_date === iso;
            });

            var tasksEl = card.querySelector('.cal-tasks');
            tasksEl.innerHTML = '';
            if (dayTasks.length === 0) {
                var empty = document.createElement('li');
                empty.className = 'cal-empty';
                empty.textContent = 'No tasks due';
                tasksEl.appendChild(empty);
            } else {
                dayTasks.forEach(function (t) {
                    var li = document.createElement('li');
                    li.className = 'class-' + t.class + (t.completed ? ' completed' : '');
                    var span = document.createElement('span');
                    span.textContent = t.text;
                    li.appendChild(span);
                    tasksEl.appendChild(li);
                });
            }
        }

        function render() {
            fillCard(prevCard, offset - 1);
            fillCard(currentCard, offset);
            fillCard(nextCard, offset + 1);
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
