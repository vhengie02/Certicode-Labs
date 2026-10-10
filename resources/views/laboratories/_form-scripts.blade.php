{{-- State for the lab form: availability, tasks and starter files. --}}
<script>
    window.labForm = function (initial) {
        return {
            availabilityMode: initial.availabilityMode || 'open',
            tasks: initial.tasks && initial.tasks.length ? initial.tasks : [{ task: '', command: '' }],
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
                this.tasks.push({ task: '', command: '' });
                this.$nextTick(() => document.getElementById(`task-${this.tasks.length - 1}`)?.focus());
            },
            removeTask(index) {
                if (this.tasks.length > 1) this.tasks.splice(index, 1);
            },
        };
    };
</script>
