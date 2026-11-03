// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

const REFCOUNT = Symbol();

class RefCountDisposable {

    constructor(target) {
        this.target = target;
        this.refCount++;
    }

    get refCount() {
        return this.target && this.target[REFCOUNT] || 0;
    }

    set refCount(count) {
        if (this.target) {
            this.target[REFCOUNT] = count;
        }
    }

    dispose() {
        if (!this.refCount) {
            return;
        }
        if (--this.refCount === 0) {
            this.target.dispose();
            delete this.target;
        }
    }
}

module.exports = RefCountDisposable;
