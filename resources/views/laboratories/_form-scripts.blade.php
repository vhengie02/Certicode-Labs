{{-- State for the lab form: availability, tasks and starter files. --}}
<script>
    // A task's check is saved as one string: plain text, or a pattern prefixed with "regex:".
    // The form edits it as a type plus a value.
    const REGEX_PREFIX = 'regex:';
    const toFormTask = (t) => {
        const command = (t.command || '').trim();
        const isRegex = command.startsWith(REGEX_PREFIX);
        return {
            task: t.task || '',
            checkType: isRegex ? 'regex' : 'text',
            checkValue: isRegex ? command.slice(REGEX_PREFIX.length) : command,
            checkOpen: command !== '',
        };
    };

    window.labForm = function (initial) {
        return {
            availabilityMode: initial.availabilityMode || 'open',
            tasks: (initial.tasks && initial.tasks.length ? initial.tasks : [{ task: '', command: '' }]).map(toFormTask),
            starterFiles: initial.starterFiles && initial.starterFiles.length
                ? initial.starterFiles
                : [{ name: '', content: '', is_primary: true, is_readonly: false }],

            init() {
                // Exactly one file opens first
                if (!this.starterFiles.some((f) => f.is_primary)) {
                    this.starterFiles[0].is_primary = true;
                }
            },
            setPrimary(index) {
                this.starterFiles.forEach((f, i) => { f.is_primary = i === index; });
            },
            addFile() {
                this.starterFiles.push({ name: '', content: '', is_primary: false, is_readonly: false });
                this.$nextTick(() => document.getElementById(`starter-name-${this.starterFiles.length - 1}`)?.focus());
            },
            removeFile(index) {
                if (this.starterFiles.length <= 1) return;
                const wasPrimary = this.starterFiles[index].is_primary;
                this.starterFiles.splice(index, 1);
                if (wasPrimary) this.starterFiles[0].is_primary = true;
            },
            addTask() {
                this.tasks.push(toFormTask({}));
                this.$nextTick(() => document.getElementById(`task-${this.tasks.length - 1}`)?.focus());
            },
            removeTask(index) {
                if (this.tasks.length > 1) this.tasks.splice(index, 1);
            },
            openCheck(index) {
                this.tasks[index].checkOpen = true;
                this.$nextTick(() => document.getElementById(`check-${index}`)?.focus());
            },
            removeCheck(index) {
                Object.assign(this.tasks[index], { checkOpen: false, checkType: 'text', checkValue: '' });
            },
            commandFor(task) {
                const value = task.checkOpen ? task.checkValue.trim() : '';
                if (value === '') return '';
                if (task.checkType === 'regex') {
                    return REGEX_PREFIX + (value.startsWith(REGEX_PREFIX) ? value.slice(REGEX_PREFIX.length) : value);
                }
                return value;
            },
            // The grader runs PHP regexes, but a pattern JavaScript can't parse is almost always a typo
            patternValid(task) {
                if (!task.checkOpen || task.checkType !== 'regex' || task.checkValue.trim() === '') return true;
                try {
                    new RegExp(task.checkValue.trim());
                    return true;
                } catch (e) {
                    return false;
                }
            },
        };
    };
</script>
