document.addEventListener('DOMContentLoaded', function () {
    const beginDate = document.getElementById('beginDate');
    const beginTime = document.getElementById('beginTime');
    const endDate = document.getElementById('endDate');
    const endTime = document.getElementById('endTime');
    const MIN_DURATION = 30; // ปรับเวลาขึ้นต่ำที่ต้องการ

    function getCurrentDate() {
        const options = { timeZone: 'Asia/Bangkok', year: 'numeric', month: '2-digit', day: '2-digit' };
        return new Intl.DateTimeFormat('en-CA', options).format(new Date());
    }

    function getCurrentTime() {
        const options = { timeZone: 'Asia/Bangkok', hour: '2-digit', minute: '2-digit', hour12: false };
        return new Intl.DateTimeFormat('en-CA', options).format(new Date());
    }
    function timeToMinutes(timeStr) {
        const [hours, minutes] = timeStr.split(':').map(Number);
        return hours * 60 + minutes;
    }

    function minutesToTime(minutes) {
        const hours = Math.floor(minutes / 60) % 24;
        const mins = minutes % 60;
        return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;
    }

    function addDaysToDate(dateStr, days) {
        const date = new Date(dateStr);
        date.setDate(date.getDate() + days);
        return date.toISOString().split('T')[0];
    }

    function calculateDuration(startDate, startTime, endDate, endTime) {
        const start = new Date(`${startDate}T${startTime}`);
        const end = new Date(`${endDate}T${endTime}`);
        return Math.floor((end - start) / (1000 * 60));
    }

    function addMinutesToTime(timeStr, minutesToAdd) {
        const totalMinutes = timeToMinutes(timeStr) + minutesToAdd;
        const days = Math.floor(totalMinutes / (24 * 60));
        const remainingMinutes = totalMinutes % (24 * 60);
        return {
            time: minutesToTime(remainingMinutes),
            dayOffset: days
        };
    }

    function isTimeInPast(dateStr, timeStr) {
        const currentDate = getCurrentDate();
        const currentTime = getCurrentTime();

        if (dateStr < currentDate) return true;
        if (dateStr === currentDate && timeStr < currentTime) return true;
        return false;
    }

    function validateBeginDateTime() {
        const currentDate = getCurrentDate();
        const currentTime = getCurrentTime();
        beginDate.min = currentDate;

        if (beginDate.value < currentDate) {
            beginDate.value = currentDate;
        }

        if (!beginTime.value) return;

        if (isTimeInPast(beginDate.value, beginTime.value)) {
            beginTime.value = currentTime;
        }

        updateEndTime();
    }

    function updateEndTime() {
        const duration = calculateDuration(
            beginDate.value, beginTime.value,
            endDate.value, endTime.value
        );

        if (duration < MIN_DURATION) {
            const result = addMinutesToTime(beginTime.value, MIN_DURATION);
            endTime.value = result.time;

            if (result.dayOffset > 0) {
                endDate.value = addDaysToDate(beginDate.value, result.dayOffset);
            } else {
                endDate.value = beginDate.value;
            }
        }

        validateEndDateTime();
    }

    function validateEndDateTime() {
        if (!beginDate.value || !beginTime.value) return;

        endDate.min = beginDate.value;
        if (endDate.value < beginDate.value) {
            endDate.value = beginDate.value;
        }

        if (endTime.value) {
            const duration = calculateDuration(
                beginDate.value, beginTime.value,
                endDate.value, endTime.value
            );

            if (duration < MIN_DURATION) {
                const result = addMinutesToTime(beginTime.value, MIN_DURATION);

                if (beginDate.value === endDate.value) {
                    if (timeToMinutes(endTime.value) < timeToMinutes(beginTime.value)) {
                        endDate.value = addDaysToDate(beginDate.value, 1);
                    } else {
                        endTime.value = result.time;
                    }
                } else if (duration < 0) {
                    endDate.value = addDaysToDate(beginDate.value, 1);
                    endTime.value = result.time;
                } else {
                    if (result.dayOffset > 0) {
                        endDate.value = addDaysToDate(beginDate.value, result.dayOffset);
                    }
                }
            }
        }
    }

    function initialize() {
        const currentDate = getCurrentDate();
        const currentTime = getCurrentTime();

        beginDate.value = currentDate;
        endDate.value = currentDate;

        beginTime.value = currentTime;
        const result = addMinutesToTime(currentTime, MIN_DURATION);
        endTime.value = result.time;
        if (result.dayOffset > 0) {
            endDate.value = addDaysToDate(currentDate, result.dayOffset);
        }

        validateBeginDateTime();

        setInterval(() => {
            if (document.activeElement !== beginTime &&
                document.activeElement !== endTime) {
                validateBeginDateTime();
            }
        }, 60000);
    }

    // Event Listeners
    beginDate.addEventListener('change', validateBeginDateTime);
    beginTime.addEventListener('change', validateBeginDateTime);
    endDate.addEventListener('change', validateEndDateTime);
    endTime.addEventListener('change', validateEndDateTime);

    initialize();
});