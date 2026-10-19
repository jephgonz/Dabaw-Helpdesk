import React from 'react';

export default function FilePicker({ id, inputRef, selectedFile, buttonText = 'Choose file', emptyText = 'No file selected', ...inputProps }) {
    return (
        <span className="file-picker">
            <input ref={inputRef} id={id} type="file" {...inputProps} />
            <span className="file-picker-button">{buttonText}</span>
            <span className={`file-picker-name${selectedFile ? ' has-file' : ''}`} title={selectedFile?.name || undefined}>
                {selectedFile?.name || emptyText}
            </span>
        </span>
    );
}
